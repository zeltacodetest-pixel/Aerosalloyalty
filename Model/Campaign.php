<?php
class Aerosalloyalty_Model_Campaign extends Core_Model_Default
{
    protected $_db_table;

    public function __construct($datas = array()) {
        parent::__construct($datas);
        $this->_db_table = new Aerosalloyalty_Model_Db_Table_Campaign();
    }

    public function upsert(array $data) {
        $value_id    = (int)$data['value_id'];
        $card_number = (string)$data['card_number'];
        $uid         = (string)$data['campaign_uid'];

        $row = $this->_db_table->fetchRow([
            'value_id = ?'    => $value_id,
            'card_number = ?' => $card_number,
            'campaign_uid = ?'=> $uid
        ]);

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($row) {
            $this->_db_table->update($data, ['aerosalloyalty_campaign_id = ?' => $row->aerosalloyalty_campaign_id]);
            $this->setData(array_merge($row->toArray(), $data));
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $this->_db_table->insert($data);
            $this->find($id);
        }
        return $this;
    }

    public function deleteByUid($value_id, $card_number, $uid) {
        return $this->_db_table->delete([
            'value_id = ?'     => (int)$value_id,
            'card_number = ?'  => (string)$card_number,
            'campaign_uid = ?' => (string)$uid
        ]);
    }

    public function listForCard($value_id, $card_number) {
        return $this->_db_table->fetchAll([
            'value_id = ?'   => (int)$value_id,
            'card_number = ?'=> (string)$card_number
        ], 'name ASC');
    }
}
