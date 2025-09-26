angular.module("starter").controller(
  "MainViewController",
  function (
    $scope,
    $stateParams,
    $ionicPopup,
    $ionicLoading,
    Aerosalloyalty,
    Customer // <-- inject Customer
  ) {
    $scope.state = "loading";
    $scope.card = null;
    $scope.campaigns = [];

    $scope.value_id = $stateParams.value_id || null;
    $scope.is_logged_in = Customer.isLoggedIn();
    $scope.customer_id = Customer.customer ? Customer.customer.id : null;
    function loading(on) {
      on ? $ionicLoading.show() : $ionicLoading.hide();
    }
    function alertMsg(msg) {
      window.alert(msg || "Error");
    }

    function bcidFromEncoding(enc) {
      enc = (enc || 'EAN13').toUpperCase();
      if (enc === 'EAN13') return 'ean13';
      if (enc === 'CODE128') return 'code128';
      return String(enc).toLowerCase();
    }

    // Reusable setter for all flows (scan, enter, create)
    function setCardFromResponse(d) {
      if (!d || !d.card) return;
      $scope.card = d.card;
      $scope.card_number = d.card.card_number || null;
      // Prefer the encoding saved on the card; fallback to current selection
      var enc = (d.card.ean_encoding || ($scope.manual && $scope.manual.ean_encoding) || 'EAN13');
      // Prefer external generator to avoid server barcode dependency
      $scope.barcode_url = (
        "https://api-bwipjs.metafloor.com/?bcid=" + bcidFromEncoding(enc) + "&text=" + encodeURIComponent($scope.card_number || '')
      );
      $scope.state = "card";
    }

    $scope.openManual = function () {
      $scope.state = "manual";
    };
    $scope.backToSetup = function () {
      $scope.state = $scope.card ? "card" : "setup";
    };

    // Load campaigns/benefits for the current card
    $scope.openCampaigns = function () {
      if (!$scope.value_id || !$scope.customer_id) return alertMsg("Missing identifiers");
      loading(true);
      Aerosalloyalty.campaigns($scope.value_id, $scope.customer_id)
        .then(function (r) {
          var d = r.data || {};
          if (d.error) throw d.message;
          $scope.campaigns = d.campaigns || [];
          $scope.state = 'campaigns';
        })
        .catch(function (e) { alertMsg((e && e.message) || e); })
        .finally(function () { loading(false); });
    };

    $scope.openScan = function () {
      if (window.SBScanner && typeof window.SBScanner.scan === "function") {
        window.SBScanner.scan(function (code) {
          loading(true);
          Aerosalloyalty.scan(
            $scope.value_id,
            $scope.customer_id,
            code,
            ($scope.manual.ean_encoding || 'EAN13').toUpperCase()
          )
            .then(function (r) {
              var d = r.data;
              if (d.error) throw d.message;
              setCardFromResponse(d);
            })
            .catch(function (e) {
              alertMsg((e && e.message) || e);
            })
            .finally(function () {
              loading(false);
              $scope.$applyAsync();
            });
        });
      } else {
        // fallback prompt
        var code = window.prompt("Scan fallback: enter code");
        if (!code) return;
        loading(true);
        Aerosalloyalty.scan(
          $scope.value_id,
          $scope.customer_id,
          code,
          ($scope.manual.ean_encoding || 'EAN13').toUpperCase()
        )
          .then(function (r) {
            var d = r.data;
            if (d.error) throw d.message;
            setCardFromResponse(d);
          })
          .catch(function (e) {
            alertMsg((e && e.message) || e);
          })
          .finally(function () {
            loading(false);
          });
      }
    };
    $scope.manual = {
      ean_encoding: "EAN13", // default
      card_number: "", // user input
    };

    // Generate a valid EAN-13 number with proper check digit
    function generateEAN13() {
      var base = '';
      for (var i = 0; i < 12; i++) base += Math.floor(Math.random() * 10);
      var sum = 0;
      for (var j = 0; j < 12; j++) {
        var d = parseInt(base.charAt(j), 10);
        sum += (j % 2 === 0) ? d : d * 3;
      }
      var check = (10 - (sum % 10)) % 10;
      return base + String(check);
    }

    // Manual card entry function
    $scope.saveManual = function (value_id, customer_id) {
      if (!$scope.manual.card_number) return alertMsg("Card number required");

      // 13 digit validation
      if ((($scope.manual.ean_encoding || 'EAN13').toUpperCase() === 'EAN13')) {
        if (!/^\d{13}$/.test($scope.manual.card_number)) {
          return alertMsg("Card number must be exactly 13 digits");
        }
      }

      loading(true);

      Aerosalloyalty.enter(
        value_id,
        $scope.manual.card_number,
        ($scope.manual.ean_encoding || 'EAN13').toUpperCase(),
        customer_id
      )
        .then(function (r) {
          var d = r.data;
          if (d.error) throw d.message;
          setCardFromResponse(d);
          $scope.manual.card_number = "";
        })
        .catch(function (e) {
          alertMsg((e && e.message) || e);
        })
        .finally(function () {
          loading(false);
        });
    };

    $scope.createVirtual = function () {
      loading(true);
      var enc = ($scope.manual && $scope.manual.ean_encoding) ? $scope.manual.ean_encoding.toUpperCase() : 'EAN13';
      var cardNum = enc === 'EAN13' ? generateEAN13() : Math.random().toString().slice(2, 14);

      Aerosalloyalty.createVirtual(
        $scope.value_id,
        enc,
        $scope.customer_id,
        cardNum
      )
        .then(function (r) {
          var d = r.data;
          if (d.error) throw d.message;
          setCardFromResponse(d);


        })
        .catch(function (e) {
          alertMsg((e && e.message) || e);
        })
        .finally(function () {
          loading(false);
        });
    };

    // Delete the linked card
    $scope.deleteCard = function () {
      if (!$scope.value_id || !$scope.customer_id)
        return alertMsg("Missing identifiers");

      // Ionic confirm popup instead of window.confirm
      var confirmPopup = $ionicPopup.confirm({
        title: 'Confirm Delete',
        template: 'Are you sure you want to delete your card?',
        okText: 'Yes',
        okType: 'button-assertive', // red button
        cancelText: 'No'
      });

      confirmPopup.then(function (res) {
        if (!res) return; // agar user "No" kare to kuch mat karo

        loading(true);
        Aerosalloyalty.deleteCard($scope.value_id, $scope.customer_id, $scope.card_number)
          .then(function (r) {
            var d = r.data || {};
            if (d.error) throw d.message;

            // success alert
            $ionicPopup.alert({
              title: 'Success',
              template: d.message || 'Card deleted',
              okText: 'OK',
              okType: 'button-balanced'
            });

            $scope.card = null;
            $scope.card_number = null;
            $scope.barcode_url = null;
            $scope.state = "setup";
          })
          .catch(function (e) {
            $ionicPopup.alert({
              title: 'Error',
              template: (e && e.message) || e,
              okText: 'OK',
              okType: 'button-assertive'
            });
          })
          .finally(function () {
            loading(false);
          });
      });
    };



    // -------------------------
    // REQUIREMENT: LOGIN CHECK
    // -------------------------
    if (Customer.isLoggedIn() && Customer.customer && Customer.customer.id) {
      $scope.is_logged_in = true;
      $scope.customer_id = Customer.customer.id;
      $scope.customer_name = Customer.customer.name;
      bootApp();
    } else {
      $scope.loginModalMP = function () {
        Customer.loginModal($scope, function () {
          $scope.is_logged_in = Customer.isLoggedIn();
          if ($scope.is_logged_in) {
            $scope.customer_id = Customer.customer.id;
            $scope.customer_name = Customer.customer.name;
            bootApp();
          }
        });
      };
      $scope.loginModalMP();
    }

    // -------------------------
    // Boot function
    // -------------------------
    function bootApp() {
      $scope.state = "loading";
      loading(true);
      Aerosalloyalty.init($scope.value_id, $scope.customer_id)
        .then(function (r) {
          var d = r.data || {};
          if (d.error) throw d.message;
          if (d.settings && d.settings.default_ean_encoding)
            $scope.manual.ean_encoding = d.settings.default_ean_encoding;

          if (d.card) {
            $scope.card = d.card;
            $scope.card_number = d.card.card_number;
            $scope.created_at = d.card.created_at;  

            var enc = (d.card.ean_encoding || $scope.manual.ean_encoding || 'EAN13');
            $scope.barcode_url =
              "https://api-bwipjs.metafloor.com/?bcid=" +
              bcidFromEncoding(enc) +
              "&text=" +
              encodeURIComponent($scope.card_number || '');
            $scope.state = "card";
          } else {
            $scope.card = null;
            $scope.card_number = null;
            $scope.created_at = null;  
            $scope.barcode_url = null;
            $scope.state = "setup";
          }
        })
        .catch(function (e) {
          alertMsg((e && e.message) || e);
        })
        .finally(function () {
          loading(false);
        });
    }

  }
);
