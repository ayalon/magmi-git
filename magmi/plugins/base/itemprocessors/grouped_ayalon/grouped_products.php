<?php
class GroupedProductsAyalon extends Magmi_ItemProcessor
{

	public function getPluginInfo()
	{
		return array(
            "name" => "Grouped producs ayalon",
            "author" => "ayalon",
            "version" => "1.0.0",
		);
	}

	public function checkRelated(&$rinfo)
	{
		if(count($rinfo["direct"])>0)
		{
			$sql="SELECT testid.sku,cpe.sku as esku FROM ".$this->arr2select($rinfo["direct"],"sku")." AS testid
    LEFT JOIN ".$this->tablename("catalog_product_entity")." as cpe ON cpe.sku=testid.sku
    WHERE testid.sku NOT LIKE '%re::%'
    HAVING esku IS NULL";
			$result=$this->selectAll($sql,$rinfo["direct"]);

			$to_delete=array();
			foreach($result as $row)
			{
				$this->log("Unknown related sku ".$row["sku"],"warning");
				$to_delete[]=$row["sku"];
			}
			$rinfo["direct"]=array_diff($rinfo["direct"],$to_delete);
		}
		return count($rinfo["direct"])+count($rinfo["re"]);

	}

	public function processItemAfterId(&$item,$params=null)
	{
		$grouped=isset($item["grouped"])?$item["grouped"]:null;
		$pid=$params["product_id"];
		$new=$params["new"];

		if(isset($grouped) && trim($grouped)!="")
		{
			$rinf=$this->getGroupInfos($grouped);
			if($new==false)
			{
				$this->deleteGroupedItems($item,$rinf["del"]);
			}
			$this->setGroupedItems($item,$rinf["add"]);
		}
	}

	public function deleteGroupedItems($item,$inf)
	{
		$joininfo=$this->buildJoinCond($item,$inf,"cpe2.sku");
		$j2=$joininfo["join"]["cpe2.sku"];
		if($j2!="")
		{
			$sql="DELETE cplai.*,cpl.*
      FROM ".$this->tablename("catalog_product_entity")." as cpe
      JOIN ".$this->tablename("catalog_product_link_type")." as cplt ON cplt.code='super'
      JOIN ".$this->tablename("catalog_product_link")." as cpl ON cpl.product_id=cpe.entity_id AND cpl.link_type_id=cplt.link_type_id
      JOIN ".$this->tablename("catalog_product_link_attribute_int")." as cplai ON cplai.link_id=cpl.link_id
      JOIN ".$this->tablename("catalog_product_entity")." as cpe2 ON cpe2.sku!=cpe.sku AND $j2
      
      WHERE cpe.sku=?";
			$this->delete($sql,array_merge($joininfo["data"]["cpe2.sku"],array($item["sku"])));
		}
	}

	public function getDirection(&$inf)
	{
		$dir="+";
		if($inf[0]=="-" || $inf[0]=="+")
		{
			$dir=$inf[0];
			$inf=substr($inf,1);
		}
		return $dir;

	}
	public function getGroupInfos($relationdef)
	{
		$relinfos=explode(",",$relationdef);
		$relskusadd=array("direct"=>array(),"re"=>array());
		$relskusdel=array("direct"=>array(),"re"=>array());
		foreach($relinfos as $relinfo)
		{
			$rinf=explode("::",$relinfo);
			if(count($rinf)==1)
			{
				if($this->getDirection($rinf[0])=="+")
				{
					$relskusadd["direct"][]=$rinf[0];
				}
				else
				{
					$relskusdel["direct"][]=$rinf[0];
				}
			}

			if(count($rinf)==2)
			{
				$dir=$this->getDirection($rinf[0]);
				if($dir=="+")
				{
					switch($rinf[0])
					{
						case "re":
							$relskusadd["re"][]=$rinf[1];
							break;
					}
				}
				else
				{
					switch($rinf[0])
					{
						case "re":
							$relskusdel["re"][]=$rinf[1];
							break;
					}
				}
			}
		}

		return array("add"=>$relskusadd,"del"=>$relskusdel);
	}

	public function buildJoinCond($item,$rinfo,$keys)
	{
		$joinconds=array();
		$joins=array();
		$klist=explode(",",$keys);
		foreach($klist as $key)
		{
			$data[$key]=array();
			$joinconds[$key]=array();
			if(count($rinfo["direct"])>0)
			{
				$joinconds[$key][]="$key IN (".$this->arr2values($rinfo["direct"]).")";
				$data[$key]=array_merge($data[$key],$rinfo["direct"]);
			}
			if(count($rinfo["re"])>0)
			{
				foreach($rinfo["re"] as $rinf)
				{
					$joinconds[$key][]="$key REGEXP ?";
					$data[$key][]=$rinf;
				}
			}
			$joins[$key] = implode(" OR ",$joinconds[$key]);
			if($joins[$key]!="")
			{
				$joins[$key]="({$joins[$key]})";
			}

		}
		return array("join"=>$joins,"data"=>$data);
	}


	public function setGroupedItems($item,$rinfo)
	{
		if($this->checkRelated($rinfo)>0)

		{
			$joininfo=$this->buildJoinCond($item,$rinfo,"cpe2.sku");
			$jinf=$joininfo["join"]["cpe2.sku"];
			if($jinf!="")
			{
				//insert into link table
				$bsql="SELECT cplt.link_type_id,cpe.entity_id as product_id,cpe2.entity_id as linked_product_id
      FROM ".$this->tablename("catalog_product_entity")." as cpe
      JOIN ".$this->tablename("catalog_product_entity")." as cpe2 ON cpe2.sku!=cpe.sku AND $jinf
      JOIN ".$this->tablename("catalog_product_link_type")." as cplt ON cplt.code='super'
      WHERE cpe.sku=?";
				$sql="INSERT IGNORE INTO ".$this->tablename("catalog_product_link")." (link_type_id,product_id,linked_product_id)  $bsql";
				$data=array_merge($joininfo["data"]["cpe2.sku"],array($item["sku"]));
				$this->insert($sql,$data);
				$this->updateLinkAttributeTable($item["sku"],$joininfo, $rinfo);
			}
		}
	}

	public function updateLinkAttributeTable($sku,$joininfo, $rinfo)
	{
		//insert into attribute link attribute int table,reusing the same relations
		$ji=$joininfo["join"];
		$data=array($sku);
		$addcond="";
		if(isset($ji["cpe.sku"]))
		{
			$addcond="OR ".$joininfo["join"]["cpe.sku"];
			$data=array_merge($data,$joininfo["data"]["cpe.sku"]);
		}

		//this enable to mass add
		$bsql="SELECT cpl.link_id,cpla.product_link_attribute_id,0 as value,cpe3.sku
         FROM ".$this->tablename("catalog_product_entity")." AS cpe
       JOIN ".$this->tablename("catalog_product_entity")." AS cpe2 ON cpe2.entity_id!=cpe.entity_id
       JOIN ".$this->tablename("catalog_product_link_type")." AS cplt ON cplt.code='super'
       JOIN ".$this->tablename("catalog_product_link_attribute")." AS cpla ON cpla.product_link_attribute_code='position' AND cpla.link_type_id=cplt.link_type_id
       JOIN ".$this->tablename("catalog_product_link") ." AS cpl ON cpl.link_type_id=cplt.link_type_id AND cpl.product_id=cpe.entity_id AND cpl.linked_product_id=cpe2.entity_id
       JOIN ".$this->tablename("catalog_product_entity")." AS cpe3 ON cpe3.entity_id=cpl.linked_product_id
       WHERE cpe.sku=? $addcond";
    
		//Save the sort order value according to the order of the skus in the field grouped
		//grouped = "7680536680117,7680536680384,7680536680469,7680536680544"
		//order = 1,2,3,4
		$relations = $this->selectAll($bsql, $data);

		foreach($relations as &$dataset) {
				
			$key = array_search($dataset['sku'], $rinfo['direct']);
			$params = array();
			$params[] = $dataset['link_id'];
			$params[] = $dataset['product_link_attribute_id'];
			$params[] = $key+1;
			$sql="INSERT IGNORE INTO ".$this->tablename("catalog_product_link_attribute_int")." (link_id,product_link_attribute_id,value) VALUES(?,?,?)";
			$this->insert($sql,$params);

		}

    //Save data in the catalog_product_relation table (kind of redundant data)
		$gsql="SELECT cpl.product_id,cpl.linked_product_id
		         FROM ".$this->tablename("catalog_product_entity")." AS cpe
		       JOIN ".$this->tablename("catalog_product_entity")." AS cpe2 ON cpe2.entity_id!=cpe.entity_id
		       JOIN ".$this->tablename("catalog_product_link_type")." AS cplt ON cplt.code='super'
		       JOIN ".$this->tablename("catalog_product_link_attribute")." AS cpla ON cpla.product_link_attribute_code='position' AND cpla.link_type_id=cplt.link_type_id
		       JOIN ".$this->tablename("catalog_product_link") ." AS cpl ON cpl.link_type_id=cplt.link_type_id AND cpl.product_id=cpe.entity_id AND cpl.linked_product_id=cpe2.entity_id
		       WHERE cpe.sku=? $addcond";
		//Relation Group
		$sql2="INSERT IGNORE INTO ".$this->tablename("catalog_product_relation")." (parent_id,child_id) $gsql";

		$this->insert($sql2,$data);

	}

	public function afterImport()
	{
		//remove maybe inserted doubles
		$cplai=$this->tablename("catalog_product_link_attribute_int");
		$sql="DELETE cplaix FROM $cplai as cplaix
      WHERE cplaix.value_id IN 
      (SELECT s1.value_id FROM 
        (SELECT cplai.link_id,cplai.value_id,MAX(cplai.value_id) as latest 
          FROM $cplai as cplai 
          GROUP BY cplai.link_id
        HAVING cplai.value_id!=latest) 
      as s1)";
		$this->delete($sql);
	}

	static public function getCategory()
	{
		return "Grouped Products";
	}
}