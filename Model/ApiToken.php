<?php
class Aerosalloyalty_Model_ApiToken extends Core_Model_Default
{
    protected $_db_table;

    public function __construct($datas = array()) {
        parent::__construct($datas);
        $this->_db_table = new Aerosalloyalty_Model_Db_Table_ApiToken();
    }

    public function validate($token, $value_id = null) {
        $where = ['token = ?' => (string)$token, 'is_active = ?' => 1];
        if ($value_id !== null) $where['value_id = ?'] = (int)$value_id;

        $row = $this->_db_table->fetchRow($where);
        if (!$row) return false;

        $this->_db_table->update(
            ['last_used_at' => date('Y-m-d H:i:s')],
            ['aerosalloyalty_api_token_id = ?' => $row->aerosalloyalty_api_token_id]
        );

        $this->setData($row->toArray());
        return true;
    }

    public function createToken($value_id, $plainToken) {
        $id = $this->_db_table->insert([
            'value_id'   => (int)$value_id,
            'token'      => $plainToken, // optionally store a hash here
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return $this->find($id);
    }
}
