<?php

class Aerosalloyalty_Public_CampaignController extends Application_Controller_Default
{
    protected function authenticateToken()
    {
        $header = trim((string)$this->getRequest()->getHeader('Authorization'));
        if (!$header || stripos($header, 'Bearer ') !== 0) {
            throw new Exception('Unauthorized', 401);
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            throw new Exception('Unauthorized', 401);
        }

        $model = new Aerosalloyalty_Model_ApiToken();
        if (!$model->validate($token)) {
            throw new Exception('Unauthorized', 401);
        }

        return $model;
    }

    protected function resolveSettings($appId)
    {
        $appId = (int)$appId;
        if ($appId <= 0) {
            throw new Exception('Missing app_id', 400);
        }

        $settings = new Aerosalloyalty_Model_Settings();
        $result   = $settings->findByAppId($appId);
        if (!$result || !$settings->getId()) {
            throw new Exception('Feature not configured for provided app_id', 404);
        }

        return $settings;
    }

    protected function parseJsonBody()
    {
        $raw = $this->getRequest()->getRawBody();
        if (!strlen(trim($raw))) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function safeLog($valueId, $direction, $status, ?array $payload = null)
    {
        try {
            $json = $payload !== null
                ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null;
            (new Aerosalloyalty_Model_WebhookLog())->log(
                (int)$valueId,
                $direction,
                (string)$this->getRequest()->getRequestUri(),
                $status,
                $json
            );
        } catch (Exception $e) {
            // Swallow logging issues to avoid breaking main flow
        }
    }

    public function postAction()
    {
        $valueId = null;

        try {
            $tokenModel = $this->authenticateToken();
            $appId      = $this->getRequest()->getParam('app_id');
            $settings   = $this->resolveSettings($appId);
            $valueId    = (int)$settings->getValueId();

            if ((int)$tokenModel->getValueId() !== $valueId) {
                throw new Exception('Token does not grant access to this feature', 403);
            }

            $body = $this->parseJsonBody();

            $cardNumber = trim((string)($this->getRequest()->getParam('card_number', $body['card_number'] ?? '')));
            if ($cardNumber === '') {
                throw new Exception('Missing card_number', 400);
            }

            $card = (new Aerosalloyalty_Model_Card())->findByValueAndNumber($valueId, $cardNumber);
            if (!$card->getId()) {
                throw new Exception('Card not found for provided value_id', 404);
            }

            $typeCode = trim((string)($this->getRequest()->getParam('campaign_type_code', $body['campaign_type_code'] ?? '')));
            if ($typeCode === '') {
                throw new Exception('Missing campaign_type_code', 400);
            }

            $type = (new Aerosalloyalty_Model_CampaignType())->findByCode($valueId, $typeCode);
            if (!$type->getId()) {
                throw new Exception('Unknown campaign_type_code', 404);
            }

            $name = trim((string)($this->getRequest()->getParam('name', $body['name'] ?? '')));
            if ($name === '') {
                throw new Exception('Missing name', 400);
            }

            $points = $this->getRequest()->getParam('points_balance', $body['points_balance'] ?? 0);
            $points = (int)$points;

            $prizes = $this->getRequest()->getParam('prizes', $body['prizes'] ?? null);
            $prizes = $prizes !== null ? (string)$prizes : null;

            $this->safeLog($valueId, 'inbound', null, [
                'method' => 'POST',
                'payload' => [
                    'app_id'             =>(int)$appId,
                    'card_number'        =>$cardNumber,
                    'campaign_type_code' =>$typeCode,
                    'name'               =>$name,
                    'points_balance'     =>$points,
                    'prizes'             =>$prizes,
                ],
            ]);

            $uid = Aerosalloyalty_Model_Campaign::generateUid($valueId, $cardNumber);

            $campaign = (new Aerosalloyalty_Model_Campaign())->upsert([
                'value_id'           => $valueId,
                'card_number'        => $cardNumber,
                'campaign_uid'       => $uid,
                'campaign_type_code' => $typeCode,
                'name'               => $name,
                'points_balance'     => $points,
                'prizes'             => $prizes,
            ]);

            $response = [
                'success'  => 1,
                'campaign' => [
                    'value_id'           => $campaign->getValueId(),
                    'card_number'        => $campaign->getCardNumber(),
                    'campaign_uid'       => $campaign->getCampaignUid(),
                    'campaign_type_code' => $campaign->getCampaignTypeCode(),
                    'name'               => $campaign->getName(),
                    'points_balance'     => (int)$campaign->getPointsBalance(),
                    'prizes'             => $campaign->getPrizes(),
                ],
            ];

            $this->safeLog($valueId, 'outbound', 201, $response);
            $this->getResponse()->setHttpResponseCode(201);
            return $this->_sendJson($response);
        } catch (Exception $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            if ($valueId !== null) {
                $this->safeLog($valueId, 'outbound', $status, ['error' => 1, 'message' => $e->getMessage()]);
            }
            $this->getResponse()->setHttpResponseCode($status);
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    public function putAction()
    {
        $valueId = null;

        try {
            $tokenModel = $this->authenticateToken();
            $appId      = $this->getRequest()->getParam('app_id');
            $settings   = $this->resolveSettings($appId);
            $valueId    = (int)$settings->getValueId();

            if ((int)$tokenModel->getValueId() !== $valueId) {
                throw new Exception('Token does not grant access to this feature', 403);
            }

            $uid = trim((string)$this->getRequest()->getParam('uid'));
            if ($uid === '') {
                throw new Exception('Missing campaign uid', 400);
            }

            $body = $this->parseJsonBody();

            $cardNumber = $this->getRequest()->getParam('card_number', $body['card_number'] ?? null);
            $cardNumber = $cardNumber !== null ? trim((string)$cardNumber) : null;

            $campaignModel = new Aerosalloyalty_Model_Campaign();
            $campaign      = $campaignModel->findByUid($valueId, $uid, $cardNumber);
            if (!$campaign->getId()) {
                $campaign = $campaignModel->findByUid($valueId, $uid);
            }

            if (!$campaign->getId()) {
                throw new Exception('Campaign not found', 404);
            }

            $cardNumber = $cardNumber ?: $campaign->getCardNumber();

            $card = (new Aerosalloyalty_Model_Card())->findByValueAndNumber($valueId, $cardNumber);
            if (!$card->getId()) {
                throw new Exception('Card not found for provided value_id', 404);
            }

            $typeCode = $this->getRequest()->getParam('campaign_type_code', $body['campaign_type_code'] ?? $campaign->getCampaignTypeCode());
            $typeCode = trim((string)$typeCode);
            if ($typeCode === '') {
                throw new Exception('Missing campaign_type_code', 400);
            }

            $type = (new Aerosalloyalty_Model_CampaignType())->findByCode($valueId, $typeCode);
            if (!$type->getId()) {
                throw new Exception('Unknown campaign_type_code', 404);
            }

            $name = $this->getRequest()->getParam('name', $body['name'] ?? $campaign->getName());
            $name = trim((string)$name);
            if ($name === '') {
                throw new Exception('Missing name', 400);
            }

            $points = $this->getRequest()->getParam('points_balance', $body['points_balance'] ?? $campaign->getPointsBalance());
            $points = (int)$points;

            $prizes = $this->getRequest()->getParam('prizes', array_key_exists('prizes', $body) ? $body['prizes'] : $campaign->getPrizes());
            $prizes = $prizes !== null ? (string)$prizes : null;

            $this->safeLog($valueId, 'inbound', null, [
                'method' => 'PUT',
                'payload' => [
                    'app_id'             => (int)$appId,
                    'uid'                => $uid,
                    'card_number'        => $cardNumber,
                    'campaign_type_code' => $typeCode,
                    'name'               => $name,
                    'points_balance'     => $points,
                    'prizes'             => $prizes,
                ],
            ]);

            $campaign = $campaignModel->upsert([
                'value_id'           => $valueId,
                'card_number'        => $cardNumber,
                'campaign_uid'       => $uid,
                'campaign_type_code' => $typeCode,
                'name'               => $name,
                'points_balance'     => $points,
                'prizes'             => $prizes,
            ]);

            $response = [
                'success'  => 1,
                'campaign' => [
                    'value_id'           => $campaign->getValueId(),
                    'card_number'        => $campaign->getCardNumber(),
                    'campaign_uid'       => $campaign->getCampaignUid(),
                    'campaign_type_code' => $campaign->getCampaignTypeCode(),
                    'name'               => $campaign->getName(),
                    'points_balance'     => (int)$campaign->getPointsBalance(),
                    'prizes'             => $campaign->getPrizes(),
                ],
            ];

            $this->safeLog($valueId, 'outbound', 200, $response);
            $this->getResponse()->setHttpResponseCode(200);
            return $this->_sendJson($response);
        } catch (Exception $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            if ($valueId !== null) {
                $this->safeLog($valueId, 'outbound', $status, ['error' => 1, 'message' => $e->getMessage()]);
            }
            $this->getResponse()->setHttpResponseCode($status);
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    public function deleteAction()
    {
        $valueId = null;

        try {
            $tokenModel = $this->authenticateToken();
            $appId      = $this->getRequest()->getParam('app_id');
            $settings   = $this->resolveSettings($appId);
            $valueId    = (int)$settings->getValueId();

            if ((int)$tokenModel->getValueId() !== $valueId) {
                throw new Exception('Token does not grant access to this feature', 403);
            }

            $uid = trim((string)$this->getRequest()->getParam('uid'));
            if ($uid === '') {
                throw new Exception('Missing campaign uid', 400);
            }

            $body = $this->parseJsonBody();

            $cardNumber = $this->getRequest()->getParam('card_number', $body['card_number'] ?? null);
            $cardNumber = $cardNumber !== null ? trim((string)$cardNumber) : null;

            $campaignModel = new Aerosalloyalty_Model_Campaign();
            $campaign      = $campaignModel->findByUid($valueId, $uid, $cardNumber);
            if (!$campaign->getId()) {
                $campaign = $campaignModel->findByUid($valueId, $uid);
            }

            if (!$campaign->getId()) {
                throw new Exception('Campaign not found', 404);
            }

            $cardNumber = $campaign->getCardNumber();

            $this->safeLog($valueId, 'inbound', null, [
                'method' => 'DELETE',
                'payload' => [
                    'app_id'      => (int)$appId,
                    'uid'         => $uid,
                    'card_number' => $cardNumber,
                ],
            ]);

            $campaignModel->deleteByUid($valueId, $cardNumber, $uid);

            $this->safeLog($valueId, 'outbound', 204, ['success' => 1]);
            $this->getResponse()->setHttpResponseCode(204);
            $this->getResponse()->setBody('');
            return;
        } catch (Exception $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            if ($valueId !== null) {
                $this->safeLog($valueId, 'outbound', $status, ['error' => 1, 'message' => $e->getMessage()]);
            }
            $this->getResponse()->setHttpResponseCode($status);
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }
}
