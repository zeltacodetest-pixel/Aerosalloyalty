<?php
class Aerosalloyalty_Model_Card extends Core_Model_Default
{
    protected $_db_table;

    public function __construct($datas = array())
    {
        parent::__construct($datas);
        $this->_db_table = new Aerosalloyalty_Model_Db_Table_Card();
    }

    /** Delete card by composite keys */
    public function deleteByValueCustomerAndNumber($value_id, $customer_id, $card_number, $hard = true)
    {
        return $this->_db_table->deleteByValueCustomerAndNumber($value_id, $customer_id, $card_number, $hard);
    }
    public function findById($id)
    {
        $this->find($id);
        return $this;
    }

    public function findByValueAndNumber($value_id, $card_number)
    {
        $row = $this->_db_table->fetchRow([
            'value_id = ?'   => (int)$value_id,
            'card_number = ?' => (string)$card_number,
            'deleted_at IS NULL'
        ]);
        if ($row) $this->setData($row->toArray());
        return $this;
    }

    /** Find a card record by card_number (globally), ignoring soft-deleted rows */
    public function findByNumber($card_number)
    {
        $row = $this->_db_table->fetchRow([
            'card_number = ?' => (string)$card_number,
            'deleted_at IS NULL'
        ]);
        if ($row) $this->setData($row->toArray());
        return $this;
    }

    public function upsertCard(array $data)
    {
        $value_id    = (int)$data['value_id'];
        $card_number = (string)$data['card_number'];

        $row = $this->_db_table->fetchRow([
            'value_id = ?'   => $value_id,
            'card_number = ?' => $card_number
        ]);

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($row) {
            $this->_db_table->update($data, ['aerosalloyalty_card_id = ?' => $row->aerosalloyalty_card_id]);
            $this->setData(array_merge($row->toArray(), $data));
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $this->_db_table->insert($data);
            $this->find($id);
        }
        return $this;
    }

    public function softDeleteById($id)
    {
        return $this->_db_table->update(
            ['deleted_at' => date('Y-m-d H:i:s')],
            ['aerosalloyalty_card_id = ?' => (int)$id]
        );
    }

    public function getByCustomer($value_id, $customer_id)
    {
        $row = $this->_db_table->fetchRow([
            'value_id = ?'   => (int)$value_id,
            'customer_id = ?' => (int)$customer_id,
            'deleted_at IS NULL'
        ]);
        if ($row) $this->setData($row->toArray());
        return $this;
    }
   public function getCardByCustomerAndValue($customer_id, $value_id)
{
    $row = $this->_db_table->getCardByCustomerAndValue($customer_id, $value_id);
    if ($row) {
        $this->setData($row);
        return $this;
    }
    return null;
}


}
