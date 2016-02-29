<?php
class AyalonSortCategoriesUtility extends Magmi_UtilityPlugin
{
	public function getPluginInfo()
	{
		return array("name"=>"03 - Ayalon Sort Categories",
					 "author"=>"ayalon",
					 "version"=>"1.0");
	}
	
	public function runUtility()
	{
	  	 
	    //Attribute ID laden
    $xpIndex = $this->getAttributeId('xp_index');
    
    echo "xp_index: " . $xpIndex;

    //SQL Befehl um die Kategorien zu sortieren.
    $sqlsort =
    "SET # init vars, set defaults
     @pos = 0,
     @last_parent = 0,
     @last_level = 0
    ;
    DROP TABLE IF EXISTS cce_adjusted;
    CREATE TEMPORARY TABLE cce_adjusted # output to temp table for review before commit
    SELECT
     a.entity_id,
     a.level,
     a.parent_id,
     a.value,
     a.position,
     a.tempxp,
     @pos := (IF((@last_parent != a.parent_id) OR (@last_level != a.level), 0, @pos) + 1) `new_position`,
     @last_level `last_level`,
     @last_parent `last_parent`,
     @last_level := CAST(a.level AS UNSIGNED) `new_last_level`,
     @last_parent := CAST(a.parent_id AS UNSIGNED) `new_last_parent`
    FROM (
     SELECT
      cce.entity_id,
      cce.level,
      cce.parent_id,
      ccev.value,
      cce.position,
      xp.value as xpindex,
      IFNULL(xp.value, 999999999999) as 'tempxp'
     FROM catalog_category_entity cce
     INNER JOIN catalog_category_entity_varchar ccev
      ON cce.entity_id = ccev.entity_id
       AND ccev.store_id = 0 # Root Store
       AND ccev.attribute_id = 31 # Category Name

     LEFT JOIN catalog_category_entity_varchar xp
     ON cce.entity_id = xp.entity_id AND xp.attribute_id = ?
     ORDER BY cce.level, cce.parent_id, tempxp + 0, ccev.value
    ) a
    ;
    SELECT * FROM cce_adjusted;

    # commit changes
    UPDATE catalog_category_entity cce
    INNER JOIN cce_adjusted a
     ON cce.entity_id = a.entity_id
    SET cce.position = a.new_position;";

    //SQL ausführen
	  $this->exec_stmt($sqlsort, array($xpIndex));

		echo "Category data sorted";
	}
	

	/**
	 * retrieves attribute  id for a given attribute name
	 * @param string $name : attribute name
	 */
	public function getAttributeId($name)
	{
    $tname=$this->tablename("eav_attribute");
    $aid=$this->selectone(
        "SELECT attribute_id FROM $tname WHERE attribute_code=? AND entity_type_id=?",
        array($name,3),
        'attribute_id');

	  return $aid;
	}
	
	public function getWarning()
	{
		return "Are you sure?, it will sort categories in the catalog!!!";
	}
	public function getShortDescription()
	{
		return "This helper sorts the categories by xp_index";	
	}
}