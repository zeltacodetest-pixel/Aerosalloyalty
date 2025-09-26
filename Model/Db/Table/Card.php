<?php
class Aerosalloyalty_Model_Db_Table_Card extends Core_Model_Db_Table
{
    protected $_name    = 'aerosalloyalty_cards';
    protected $_primary = 'aerosalloyalty_card_id';

    /** Delete by composite: value_id + customer_id + card_number. */
    public function deleteByValueCustomerAndNumber($value_id, $customer_id, $card_number, $hard = true)
    {
        $where = [
            'value_id = ?'    => (int)$value_id,
            'customer_id = ?' => (int)$customer_id,
            'card_number = ?' => (string)$card_number,
        ];

        if ($hard) {
            return $this->delete($where);
        }
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')], $where);
    }
 public function getCardByCustomerAndValue($customer_id, $value_id)
{
    $select = $this->select()
        ->from($this->_name) // table ka naam directly use karo
        ->where('customer_id = ?', (int)$customer_id)
        ->where('value_id = ?', (int)$value_id)
        ->limit(1);

    $row = $this->fetchRow($select);

    return $row ? $row->toArray() : null;
}


}
