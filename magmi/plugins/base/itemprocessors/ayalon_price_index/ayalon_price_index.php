<?php

class AyalonPriceIndex extends Magmi_ItemProcessor {

  public function getPluginInfo() {
    return array(
      "name" => "Index special prices",
      "author" => "ayalon",
      "version" => "1.0.0",
    );
  }

  public function processItemAfterImport(&$item, $params = null) {

    /*
     * After the import we delete all entries for that article in the product index table
     * That is the only know way to rebuild all prices correctly
     * After the import, the reindexing of the prices will happen and the entries are correctly rebuild
     */

    if (count($item) > 0) {
      $pid=$params["product_id"];

      if(!empty($item['special_price'])) {
        $priceidx = $this->tablename("catalog_product_index_price");
        $sql = "DELETE FROM $priceidx WHERE entity_id=?";
        $this->delete($sql, $pid);
      }

    }
    return true;
  }
  static public function getCategory() {
    return "Grouped Products";
  }
}