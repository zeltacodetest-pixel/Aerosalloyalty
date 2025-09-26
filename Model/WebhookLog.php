<?php
class Aerosalloyalty_Model_WebhookLog extends Core_Model_Default
{
    protected $_db_table;

    public function __construct($datas = array()) {
        parent::__construct($datas);
        $this->_db_table = new Aerosalloyalty_Model_Db_Table_WebhookLog();
    }

    public function log($value_id, $direction, $endpoint, $http_status = null, $payload = null) {
        $id = $this->_db_table->insert([
            'value_id'   => (int)$value_id,
            'direction'  => $direction,         // 'outbound'|'inbound'
            'endpoint'   => (string)$endpoint,
            'http_status'=> $http_status !== null ? (int)$http_status : null,
            'payload'    => $payload,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return $this->find($id);
    }
}
