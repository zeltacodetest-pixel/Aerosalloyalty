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

    public function getActiveTokenForValue($value_id) {
        $row = $this->_db_table->fetchRow([
            'value_id = ?' => (int)$value_id,
            'is_active = ?' => 1
        ], 'aerosalloyalty_api_token_id DESC');

        if ($row) {
            $this->setData($row->toArray());
        } else {
            $this->setData([]);
        }

        return $this;
    }

    public function deactivateAllForValue($value_id) {
        return $this->_db_table->update(
            ['is_active' => 0],
            ['value_id = ?' => (int)$value_id]
        );
    }

    public static function generateTokenString($length = 48) {
        $length = max(16, (int)$length);
        $bytesLength = (int)ceil($length / 2);
        return substr(bin2hex(random_bytes($bytesLength)), 0, $length);
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

    public function ensureToken($value_id) {
        $this->getActiveTokenForValue($value_id);
        if ($this->getId()) {
            return $this;
        }

        $plain = self::generateTokenString();
        return $this->createToken($value_id, $plain);
    }

    public function regenerateToken($value_id) {
        $this->deactivateAllForValue($value_id);
        $plain = self::generateTokenString();
        return $this->createToken($value_id, $plain);
    }
}
