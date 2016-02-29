<?php
class AyalonClearProductsUtility extends Magmi_UtilityPlugin
{
	public function getPluginInfo()
	{
		return array("name"=>"01 - Ayalon Delete Products",
					 "author"=>"ayalon",
					 "version"=>"1.0");
	}
	
	public function runUtility()
	{
		
	  # Alles darf gelöscht werden ausser der Tabelle
	  # "catalog_product_entity"
	  # In dieser Tabelle ist die Zuweisung von SKU zur internen ID gespeichert.
	  	 
	  
	  $sql="SET FOREIGN_KEY_CHECKS = 0";
		$this->exec_stmt($sql);
		$tables=array("catalog_product_bundle_option",
      "catalog_category_product",
      "catalog_category_product_index",
      "catalog_product_bundle_option",
      "catalog_product_bundle_option_value",
      "catalog_product_bundle_selection",
      "catalog_product_bundle_selection_price",
      "catalog_product_bundle_stock_index",
      "catalog_product_enabled_index",
      "catalog_product_entity_datetime",
      "catalog_product_entity_decimal",
      "catalog_product_entity_gallery",
      "catalog_product_entity_int",
      "catalog_product_entity_media_gallery",
      "catalog_product_entity_media_gallery_value",
      "catalog_product_entity_text",
      "catalog_product_entity_tier_price",
      "catalog_product_entity_varchar",
      "catalog_product_index_eav",
      "catalog_product_index_eav_idx",
      "catalog_product_index_price",
      "catalog_product_index_price_idx",
      "catalog_product_link",
      "catalog_product_link_attribute",
      "catalog_product_link_attribute_decimal",
      "catalog_product_link_attribute_int",
      "catalog_product_link_attribute_varchar",
      "catalog_product_link_type",
      "catalog_product_option",
      "catalog_product_option_price",
      "catalog_product_option_title",
      "catalog_product_option_type_price",
      "catalog_product_option_type_title",
      "catalog_product_option_type_value",
      "catalog_product_relation",
      "catalog_product_super_attribute_label",
      "catalog_product_super_attribute_pricing",
      "catalog_product_super_attribute",
      "catalog_product_super_link",
      "catalog_product_enabled_index",
      "catalog_product_website",
      "cataloginventory_stock_item",
      "cataloginventory_stock_status",
      "cataloginventory_stock",
      "catalogsearch_fulltext",
      "catalogindex_price",
      "catalogindex_eav",
      "cataloginventory_stock_status_idx",
      "core_url_rewrite",
      "dataflow_batch_import",
      "index_event",
      "index_process_event");
		
		//clear flat catalogs index
		$stmt=$this->exec_stmt("SHOW TABLES LIKE '".$this->tablename('catalog_product_flat')."%'",NULL,false);
		while($row=$stmt->fetch(PDO::FETCH_NUM))
		{
			$this->exec_stmt("TRUNCATE TABLE ".$row[0]);
		}
		
		foreach($tables as $table)
		{
			$this->exec_stmt("TRUNCATE TABLE `".$this->tablename($table)."`");
		}
		
		//Inserts
		
		$sql_insert ="INSERT  INTO `catalog_product_link_type`(`link_type_id`,`code`) VALUES (1,'relation'),(2,'bundle'),(3,'super'),(4,'up_sell'),(5,'cross_sell');";
		$sql_insert .="INSERT  INTO `catalog_product_link_attribute`(`product_link_attribute_id`,`link_type_id`,`product_link_attribute_code`,`data_type`) VALUES (1,2,'qty','decimal'),(2,1,'position','int'),(3,4,'position','int'),(4,5,'position','int'),(6,1,'qty','decimal'),(7,3,'position','int'),(8,3,'qty','decimal');";
		$sql_insert .="INSERT  INTO `cataloginventory_stock`(`stock_id`,`stock_name`) VALUES (1,'Default');";
		$this->exec_stmt($sql_insert);
		
		$sql="SET FOREIGN_KEY_CHECKS = 1";

		$this->exec_stmt($sql);
		echo "Product data cleared";
	}
	
	public function getWarning()
	{
		return "Are you sure?, it will delete all products except the id-sku mapping in catalog!!!";
	}
	public function getShortDescription()
	{
		return "This helper clears all products";	
	}
}