<?php
class Aerosalloyalty_Model_Settings extends Core_Model_Default
{
    protected $_db_table;

    public function __construct($datas = array()) {
        parent::__construct($datas);
        $this->_db_table = new Aerosalloyalty_Model_Db_Table_Settings();
    }

    public function findByValueId($value_id) {
        $row = $this->_db_table->fetchRow(['value_id = ?' => (int)$value_id]);
        if ($row) $this->setData($row->toArray());
        return $this;
    }

    public function upsert(array $data) {
        $value_id = (int)$data['value_id'];
        $row = $this->_db_table->fetchRow(['value_id = ?' => $value_id]);

        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($row) {
            $this->_db_table->update($data, ['aerosalloyalty_settings_id = ?' => $row->aerosalloyalty_settings_id]);
            $this->setData(array_merge($row->toArray(), $data));
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $this->_db_table->insert($data);
            $this->find($id);
        }
        return $this;
    }
}
