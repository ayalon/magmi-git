<?php
class AyalonClearCategoryUtility extends Magmi_UtilityPlugin
{
	public function getPluginInfo()
	{
		return array("name"=>"02 - Ayalon Delete Categories",
					 "author"=>"ayalon",
					 "version"=>"1.0");
	}
	
	public function runUtility()
	{
	  	 
	  $sql="SET FOREIGN_KEY_CHECKS = 0";
		$this->exec_stmt($sql);
		$tables=array("catalog_product_bundle_option",
      "catalog_category_entity",
      "catalog_category_entity_datetime",
      "catalog_category_entity_decimal",
      "catalog_category_entity_int",
      "catalog_category_entity_text",
      "catalog_category_entity_varchar",
      "catalog_category_product",
      "catalog_category_product_index",);

		
		foreach($tables as $table)
		{
			$this->exec_stmt("TRUNCATE TABLE `".$this->tablename($table)."`");
		}
		
		//clear flat catalogs index
		$stmt=$this->exec_stmt("SHOW TABLES LIKE '".$this->tablename('catalog_category_flat_store')."%'",NULL,false);
		while($row=$stmt->fetch(PDO::FETCH_NUM))
		{
		  $this->exec_stmt("TRUNCATE TABLE ".$row[0]);
		}
		
		$sql_insert  = "insert  into `catalog_category_entity`(`entity_id`,`entity_type_id`,`attribute_set_id`,`parent_id`,`created_at`,`updated_at`,`path`,`position`,`level`,`children_count`) values (1,3,0,0,'0000-00-00 00:00:00','2009-02-20 00:25:34','1',1,0,1),(2,3,3,0,'2009-02-20 00:25:34','2009-02-20 00:25:34','1/2',1,1,0);";
		$sql_insert .= "insert  into `catalog_category_entity_int`(`value_id`,`entity_type_id`,`attribute_id`,`store_id`,`entity_id`,`value`) values (1,3,32,0,2,1),(2,3,32,1,2,1);";
		$sql_insert .= "insert  into `catalog_category_entity_varchar`(`value_id`,`entity_type_id`,`attribute_id`,`store_id`,`entity_id`,`value`) values (1,3,31,0,1,'Root Catalog'),(2,3,33,0,1,'root-catalog'),(3,3,31,0,2,'Default Category'),(4,3,39,0,2,'PRODUCTS'),(5,3,33,0,2,'default-category');";
		
		$this->exec_stmt($sql_insert);
		
		$sql="SET FOREIGN_KEY_CHECKS = 1";

		$this->exec_stmt($sql);
		echo "Category data cleared";
	}
	
	public function getWarning()
	{
		return "Are you sure?, it will delete categories in the catalog!!!";
	}
	public function getShortDescription()
	{
		return "This helper clears the categories";	
	}
}