<?php

class Aerosalloyalty_ApplicationController extends Application_Controller_Default
{
    public function viewAction()
    {
        $this->loadPartials();
    }

    /** INIT: returns all data needed by the editor (settings + types); campaigns optional */
    public function initAction()
    {
        try {
            $value_id = (int)$this->getRequest()->getParam('value_id');
            if (!$value_id) throw new Exception('Missing value_id');
            $settings = (new Aerosalloyalty_Model_Settings())->findAll(['value_id' => $value_id])->toArray();
            $types    = (new Aerosalloyalty_Model_CampaignType())->findAll(['value_id' => $value_id])->toArray();
            $Campaign = new Aerosalloyalty_Model_Campaign();
            $campaigns = $Campaign->findAll(['value_id' => $value_id])->toArray();
            return $this->_sendJson([
                'success' => 1,
                'settings' => $settings ? $settings[0] : [],
                'types' => $types,
                'campaigns' => $campaigns
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** SETTINGS — save (POST) */
    public function saveSettingsAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }
        try {
            $p = $this->getRequest()->getPost();
            $value_id = (int)($p['value_id'] ?? 0);
            if (!$value_id) throw new Exception('Missing value_id');

            $data = [
                'value_id'              => $value_id,
                'app_id'                => (int)($p['app_id'] ?? 0),
                'default_ean_encoding'  => $p['default_ean_encoding'] ?? 'EAN13',
                'enable_check_benefits' => !empty($p['enable_check_benefits']) ? 1 : 0,
                'webhook_url'           => strlen(trim($p['webhook_url'] ?? '')) ? trim($p['webhook_url']) : null,
            ];

            (new Aerosalloyalty_Model_Settings())->upsert($data);
            return $this->_sendJson(['success' => 1, 'message' => p__('Aerosalloyalty', 'Settings saved')]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** CAMPAIGN TYPES — list (GET) */
    public function listTypesAction()
    {
        try {
            $value_id = (int)$this->getRequest()->getParam('value_id');
            if (!$value_id) throw new Exception('Missing value_id');

            $types = (new Aerosalloyalty_Model_CampaignType())->allForValue($value_id);
            return $this->_sendJson([
                'success' => 1,
                'types' => array_map(function ($t) {
                    return $t->getData();
                }, iterator_to_array($types ?: []))
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** CAMPAIGN TYPE — save (POST) create/update by (value_id, code) */
    public function saveTypeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }
        try {
            $p = $this->getRequest()->getPost();
            $value_id = (int)($p['value_id'] ?? 0);
            if (!$value_id) throw new Exception('Missing value_id');

            $data = [
                'value_id' => $value_id,
                'code'     => trim($p['code'] ?? ''),
                'name'     => trim($p['name'] ?? ''),
                'icon'     => strlen(trim($p['icon'] ?? '')) ? trim($p['icon']) : null
            ];
            if (!$data['code'] || !$data['name']) throw new Exception('Code and Name are required');

            (new Aerosalloyalty_Model_CampaignType())->upsert($data);
            return $this->_sendJson(['success' => 1, 'message' => p__('Aerosalloyalty', 'Type saved')]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** CAMPAIGN TYPE — delete (POST by id) */
    public function deleteTypeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }
        try {
            $id = (int)($this->getRequest()->getPost('id') ?? 0);
            if (!$id) throw new Exception('Missing id');

            $db = new Aerosalloyalty_Model_Db_Table_CampaignType();
            $db->delete(['aerosalloyalty_campaign_type_id = ?' => $id]);

            return $this->_sendJson(['success' => 1, 'message' => p__('Aerosalloyalty', 'Type deleted')]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** CAMPAIGNS — list (GET, optional card_number filter) */
    public function listCampaignsAction()
    {
        try {
            $value_id = (int)$this->getRequest()->getParam('value_id');
            if (!$value_id) throw new Exception('Missing value_id');

            $card_number = trim($this->getRequest()->getParam('card_number', ''));

            $db = new Aerosalloyalty_Model_Db_Table_Campaign();
            $sel = $db->select()->from($db->info('name'))
                ->where('value_id = ?', $value_id)
                ->order('name ASC');

            if ($card_number !== '') {
                $sel->where('card_number LIKE ?', $card_number . '%');
            }

            $rows = $db->fetchAll($sel);
            return $this->_sendJson([
                'success' => 1,
                'campaigns' => array_map(function ($c) {
                    return $c->getData();
                }, $rows)
            ]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }

    /** CAMPAIGN — delete (POST by value_id, card_number, campaign_uid) */
    public function deleteCampaignAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_sendJson(['error' => 1, 'message' => 'Invalid request']);
        }
        try {
            $p = $this->getRequest()->getPost();
            $value_id    = (int)($p['value_id'] ?? 0);
            $card_number = trim($p['card_number'] ?? '');
            $uid         = trim($p['campaign_uid'] ?? '');

            if (!$value_id || !$card_number || !$uid) {
                throw new Exception('Missing required parameters');
            }

            (new Aerosalloyalty_Model_Campaign())->deleteByUid($value_id, $card_number, $uid);
            return $this->_sendJson(['success' => 1, 'message' => p__('Aerosalloyalty', 'Campaign deleted')]);
        } catch (Exception $e) {
            return $this->_sendJson(['error' => 1, 'message' => $e->getMessage()]);
        }
    }
}
