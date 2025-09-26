<?php

class Aerosalloyalty_Mobile_ViewController extends Application_Controller_Mobile_Default
{
    /* ---------- VIEW (Ionic template) ---------- */
    public function indexAction()
    {
        try {
            $this->getLayout()
                ->setBaseRender('content', 'aerosalloyalty/l1/main.html'); // or view.html if you prefer
        } catch (Exception $e) {
            $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /* ---------- JSON: helpers ---------- */
    // Note: login is handled on the app side; endpoints accept customer_id explicitly.

    protected function getSettings($value_id)
    {
        $m = (new Aerosalloyalty_Model_Settings())->findByValueId($value_id);
        return $m && $m->getId() ? $m : new Aerosalloyalty_Model_Settings([
            'value_id' => $value_id,
            'default_ean_encoding'  => 'EAN13',
            'enable_check_benefits' => 1,
            'webhook_url'           => null
        ]);
    }

    protected function loadCard($value_id, $customer_id)
    {
        $card = (new Aerosalloyalty_Model_Card())->find(['value_id'=>$value_id, 'customer_id'=> $customer_id]);
        return ($card && $card->getId()) ? $card : null;
    }

    protected function cardUpsert($value_id, $customer_id, $card_number, $ean_encoding, $is_virtual = 0)
    {
        $card = new Aerosalloyalty_Model_Card();

        $card->upsertCard([
            'value_id'    => (int)$value_id,
            'customer_id' => (int)$customer_id,
            'card_number' => (string)$card_number,
            'ean_encoding' => (string)$ean_encoding,
            'is_virtual'  => (int)$is_virtual,
            'deleted_at'  => null
        ]);
        return $card;
    }

    protected function saveCardFile($value_id, $customer_id, $card_number, $ean_encoding)
    {
        try {
            $base = realpath(__DIR__ . '/../../'); // repo root
            if (!$base) return; // bail quietly
            $dir = $base . '/resources/cards';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (!is_dir($dir) || !is_writable($dir)) return;
            $payload = [
                'value_id'    => (int)$value_id,
                'customer_id' => (int)$customer_id,
                'card_number' => (string)$card_number,
                'ean_encoding' => (string)$ean_encoding,
                'saved_at'    => date('c')
            ];
            $file = $dir . '/card_' . $value_id . '_' . $customer_id . '.json';
            @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            // ignore file save errors
        }
    }

    protected function generateCardNumber($encoding = 'EAN13')
    {
        $encoding = strtoupper($encoding ?: 'EAN13');
        if ($encoding === 'EAN13') {
            $base = '';
            for ($i = 0; $i < 12; $i++) $base .= mt_rand(0, 9);
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $d = (int)$base[$i];
                $sum += ($i % 2 === 0) ? $d : $d * 3;
            }
            $check = (10 - ($sum % 10)) % 10;
            return $base . $check;
        }
        return strtoupper(substr(sha1(uniqid('', true)), 0, 12));
    }

    protected function webhookNotify($value_id, $card_number, array $customer)
    {
        try {
            $settings = $this->getSettings($value_id);
            $url = trim((string)$settings->getWebhookUrl());
            if (!$url) return;

            $payload = [
                'value_id'    => (int)$value_id,
                'card_number' => (string)$card_number,
                'customer'    => $customer,
                'event'       => 'card_linked'
            ];

            $client = new Zend_Http_Client($url, ['timeout' => 6]);
            $client->setHeaders('Content-Type', 'application/json; charset=utf-8');
            $client->setRawData(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json');
            $resp = $client->request(Zend_Http_Client::POST);
            $status = $resp ? $resp->getStatus() : null;

            (new Aerosalloyalty_Model_WebhookLog())->log($value_id, 'outbound', $url, $status, json_encode($payload));
        } catch (Exception $e) {
            (new Aerosalloyalty_Model_WebhookLog())->log($value_id, 'outbound', isset($url) ? $url : '', null, json_encode(['error' => $e->getMessage()]));
        }
    }

    /* ---------- JSON: endpoints ---------- */

    // GET /aerosalloyalty/mobile_view/init?value_id=...
    public function initAction()
    {
        try {
            $value_id = (int)$this->getRequest()->getParam('value_id');
            if (!$value_id) throw new Exception('Missing value_id');
            $customer_id =  (int)$this->getRequest()->getParam('customer_id');
            $settings = $this->getSettings($value_id)->getData();
            $card = null;
            try {
            if ($c = (new Aerosalloyalty_Model_Card())->getCardByCustomerAndValue($customer_id, $value_id)) {
                        $card = $c->getData();

                        $barcode_url = $this->getRequest()->getBaseUrl()
                            . '/aerosalloyalty/mobile_view/barcode?number='
                            . urlencode($card['card_number'])
                            . '&encoding=' . urlencode($card['ean_encoding'] ?? 'EAN13');

                        $card['barcode_image'] = $barcode_url;
                    }
            } catch (Exception $e) { 
                $card = null; // ignore card load errors
            }

            return $this->_sendJson([
                'success' => 1,
                'settings' => [
                    'default_ean_encoding' => $settings['default_ean_encoding'] ?? 'EAN13',
                    'enable_check_benefits' => (int)($settings['enable_check_benefits'] ?? 1)
                ],
                'card' => $card,
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }
    public function barcodeAction()
    {
        try {
            $number   = $this->getRequest()->getParam('number');
            $encoding = strtoupper($this->getRequest()->getParam('encoding', 'EAN13'));

            if (!$number) {
                return $this->_sendJson([
                    "error"   => 1,
                    "message" => "No number provided"
                ]);
            }

            // PNG header
            header("Content-Type: image/png");

            // Generate barcode using Zend_Barcode
            Zend_Barcode::factory(
                $encoding,   // encoding e.g. EAN13
                'image',     // renderer
                ['text' => $number],
                []
            )->render();

            exit; // very important to stop framework from sending JSON/layout
        } catch (Exception $e) {
            return $this->_sendJson([
                "error"   => 1,
                "message" => $e->getMessage()
            ]);
        }
    }


    // POST /aerosalloyalty/mobile_view/enter
    public function enterAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }

        try {
            $value_id     = (int)$this->getRequest()->getPost('value_id');
            $customer_id  = (int)$this->getRequest()->getPost('customer_id');
            $card_number  = trim($this->getRequest()->getPost('card_number'));
            $ean_encoding = strtoupper(trim($this->getRequest()->getPost('ean_encoding') ?: 'EAN13'));

            if (!$value_id || !$customer_id || !$card_number) {
                throw new Exception('Missing value_id, customer_id, or card_number');
            }

            list($card, $barcode_url) = $this->linkCardValidated($value_id, $customer_id, $card_number, $ean_encoding, 0);

            return $this->_sendJson([
                'success' => 1,
                'card' => array_merge($card->getData(), [
                    'card_number'   => $card_number,
                    'barcode_image' => $barcode_url
                ]),
                'message' => p__('Aerosalloyalty', 'Card linked successfully')
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }



    /**
     * Validate a full 13-digit EAN number.
     */
    private function validateEAN13($number)
    {
        if (!preg_match('/^\d{13}$/', $number)) {
            return false;
        }
        $digits = str_split($number);
        $checkDigit = array_pop($digits);
        $sum = 0;
        foreach ($digits as $i => $d) {
            $sum += ($i % 2 ? $d * 3 : $d);
        }
        $calculated = (10 - ($sum % 10)) % 10;
        return $checkDigit == $calculated;
    }

    /**
     * Common flow to validate, enforce uniqueness, upsert, save snapshot file and build barcode url.
     * Returns array [$cardModel, $barcodeUrl]
     */
    private function linkCardValidated($value_id, $customer_id, $card_number, $ean_encoding, $is_virtual = 0)
    {
        $value_id = (int)$value_id;
        $customer_id = (int)$customer_id;
        $card_number = (string)$card_number;
        $ean_encoding = strtoupper($ean_encoding ?: 'EAN13');

        if (!$value_id || !$customer_id || !$card_number) {
            throw new Exception('Missing value_id, customer_id or card number');
        }

        if ($ean_encoding === 'EAN13') {
            if (!preg_match('/^\d{13}$/', $card_number)) {
                throw new Exception('Card number must be exactly 13 digits');
            }
            if (!$this->validateEAN13($card_number)) {
                throw new Exception('Invalid EAN-13 card number (wrong check digit)');
            }
        }

        // Enforce one card per customer per value
        $existingForCustomer = (new Aerosalloyalty_Model_Card())->getByCustomer($value_id, $customer_id);
        if ($existingForCustomer && $existingForCustomer->getId()) {
            throw new Exception(p__('Aerosalloyalty', 'You already have a linked card.'));
        }

        // Global uniqueness of card number (ignoring soft-deleted)
        $existsNumber = (new Aerosalloyalty_Model_Card())->findByNumber($card_number);
        if ($existsNumber && $existsNumber->getId()) {
            throw new Exception(p__('Aerosalloyalty', 'This card number is already in use.'));
        }

        // Persist
        $card = $this->cardUpsert($value_id, $customer_id, $card_number, $ean_encoding, (int)$is_virtual);
        $this->saveCardFile($value_id, $customer_id, $card_number, $ean_encoding);

        $barcode_url = $this->getRequest()->getBaseUrl()
            . '/aerosalloyalty/mobile_view/barcode?number=' . urlencode($card_number)
            . '&encoding=' . urlencode($ean_encoding);

        // Fire webhook notification (best-effort)
        try {
            $customer = [];
            try {
                if (class_exists('Customer_Model_Customer')) {
                    $cm = new Customer_Model_Customer();
                    $cm->find($customer_id);
                    if ($cm && $cm->getId()) {
                        $first = null;
                        $last = null;
                        $email = null;
                        if (method_exists($cm, 'getFirstname')) $first = (string)$cm->getFirstname();
                        elseif (method_exists($cm, 'getFirstName')) $first = (string)$cm->getFirstName();
                        if (method_exists($cm, 'getLastname')) $last = (string)$cm->getLastname();
                        elseif (method_exists($cm, 'getLastName')) $last = (string)$cm->getLastName();
                        if (method_exists($cm, 'getEmail')) $email = (string)$cm->getEmail();

                        $customer = [
                            'id'         => (int)$cm->getId(),
                            'first_name' => $first,
                            'last_name'  => $last,
                            'email'      => $email,
                        ];
                    }
                }
            } catch (Exception $e) { /* ignore customer fetch errors */
            }
            $this->webhookNotify($value_id, $card_number, $customer);
        } catch (Exception $e) { /* swallow webhook errors */
        }

        return [$card, $barcode_url];
    }





    // POST /aerosalloyalty/mobile_view/scan
    public function scanAction()
    {
        if (!$this->getRequest()->isPost()) return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        try {
            $value_id     = (int)$this->getRequest()->getPost('value_id');
            $customer_id  = (int)$this->getRequest()->getPost('customer_id');
            $code         = trim($this->getRequest()->getPost('code'));
            $ean_encoding = strtoupper(trim($this->getRequest()->getPost('ean_encoding') ?: 'EAN13'));
            if (!$value_id || !$customer_id || !$code) throw new Exception('Missing value_id, customer_id or code');

            list($card, $barcode_url) = $this->linkCardValidated($value_id, $customer_id, $code, $ean_encoding, 0);

            return $this->_sendJson([
                'success' => 1,
                'card' => array_merge($card->getData(), [
                    'card_number'   => $code,
                    'barcode_image' => $barcode_url
                ]),
                'message' => p__('Aerosalloyalty', 'Card scanned and linked')
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    // POST /aerosalloyalty/mobile_view/create-virtual
    public function createVirtualAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson([
                'error' => 1,
                'message' => 'Invalid request'
            ]);
        }

        try {
            $value_id     = (int)$this->getRequest()->getPost('value_id');
            $customer_id  = (int)$this->getRequest()->getPost('customer_id');
            $card_number  = trim($this->getRequest()->getPost('card_number'));
            $ean_encoding = strtoupper(trim($this->getRequest()->getPost('ean_encoding') ?: 'EAN13'));
            if (!$value_id) {
                throw new Exception('Missing value_id');
            }
            if (!$customer_id) {
                throw new Exception('Missing customer_id');
            }
            if (!$card_number) {
                throw new Exception('Missing card_number');
            }

            list($card, $barcode_url) = $this->linkCardValidated($value_id, $customer_id, $card_number, $ean_encoding, 1);

            return $this->_sendJson([
                'success' => 1,
                'card' => array_merge($card->getData(), [
                    'card_number'   => $card_number,
                    'barcode_image' => $barcode_url
                ]),
                'message' => p__('Aerosalloyalty', 'Virtual card created')
            ]);
        } catch (Exception $e) {
            return $this->_sendJson([
                'error' => 1,
                'message' => $e->getMessage()
            ]);
        }
    }


    // POST /aerosalloyalty/mobile_view/delete-card
    public function deleteCardAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }

        try {
            $value_id    = (int)$this->getRequest()->getPost('value_id');
            $cust_id     = (int)$this->getRequest()->getPost('customer_id');
            $card_number = trim((string)$this->getRequest()->getPost('card_number'));

            if (!$value_id)    throw new Exception('Missing value_id');
            if (!$cust_id)     throw new Exception('Missing customer_id');
            if ($card_number === '') throw new Exception('Missing card_number');

            $Card = new Aerosalloyalty_Model_Card();
            $deleted = $Card->deleteByValueCustomerAndNumber($value_id, $cust_id, $card_number, true); // true = hard delete

            if ($deleted) {
                return $this->_sendJson([
                    'success' => 1,
                    'message' => p__('Aerosalloyalty', 'Card(s) deleted successfully')
                ]);
            }

            throw new Exception(p__('Aerosalloyalty', 'No card to delete'));
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }




    // GET /aerosalloyalty/mobile_view/campaigns?value_id=...
    public function campaignsAction()
    {
        try {
            $value_id = (int)$this->getRequest()->getParam('value_id');
            if (!$value_id) throw new Exception('Missing value_id');

            $cust_id = (int)$this->getRequest()->getParam('customer_id');
            $card = $this->loadCard($value_id, $cust_id);
            if (!$card) return $this->_sendJson(['success' => 1, 'campaigns' => [], 'message' => p__('Aerosalloyalty', 'No card linked')]);

            $rows = (new Aerosalloyalty_Model_Campaign())->listForCard($value_id, $card->getCardNumber());
            $types = (new Aerosalloyalty_Model_CampaignType())->allForValue($value_id);
            $map = [];
            if ($types) foreach ($types as $t) $map[$t->getCode()] = ['name' => $t->getName(), 'icon' => $t->getIcon()];

            $list = [];
            foreach ($rows as $c) {
                $d = $c->getData();
                $code = $d['campaign_type_code'] ?? '';
                $d['campaign_type_name'] = isset($map[$code]) ? $map[$code]['name'] : $code;
                $d['campaign_type_icon'] = isset($map[$code]) ? $map[$code]['icon'] : null;
                $list[] = $d;
            }

            return $this->_sendJson(['success' => 1, 'card_number' => $card->getCardNumber(), 'campaigns' => $list]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }
}
