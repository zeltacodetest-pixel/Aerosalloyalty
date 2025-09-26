angular
  .module("starter")
  .factory("Aerosalloyalty", function ($http, Url, $httpParamSerializerJQLike) {
    var api = Url.get("aerosalloyalty/mobile_view/");
    var svc = {
      init: function (value_id, customer_id) {
        return $http.get(api + "init", { params: { value_id: value_id, customer_id: customer_id } });
      },
      enter: function (value_id, card_number, ean, customer_id) {
        return $http.post(
          api + "enter",
          $httpParamSerializerJQLike({
            value_id: value_id,
            customer_id: customer_id,
            card_number: card_number,
            ean_encoding: ean || "EAN13",
          }),
          { headers: { "Content-Type": "application/x-www-form-urlencoded" } }
        );
      },
      createVirtual: function (value_id, ean, customer_id, card_number) {
        return $http.post(
          api + "create-virtual",
          $httpParamSerializerJQLike({
            value_id: value_id,
            ean_encoding: ean || "EAN13",
            customer_id: customer_id,
            card_number: card_number,  
          }),
          { headers: { "Content-Type": "application/x-www-form-urlencoded" } }
        );
      },
      scan: function (value_id, customer_id, code, ean) {
        return $http.post(
          api + "scan",
          $httpParamSerializerJQLike({
            value_id: value_id,
            customer_id: customer_id,
            code: code,
            ean_encoding: ean || "EAN13",
          }),
          { headers: { "Content-Type": "application/x-www-form-urlencoded" } }
        );
      },

      deleteCard: function (value_id, customer_id, card_number) {
        return $http.post(
          api + "delete-card",
          $httpParamSerializerJQLike({
            value_id: value_id,
            customer_id: customer_id,
            card_number: card_number || null,
          }),
          { headers: { "Content-Type": "application/x-www-form-urlencoded" } }
        );
      },
      campaigns: function (value_id, customer_id) {
        return $http.get(api + "campaigns", { params: { value_id: value_id, customer_id: customer_id } });
      },
    };
    return svc;
  });
