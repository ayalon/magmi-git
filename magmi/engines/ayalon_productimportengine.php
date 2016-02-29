<?php

/**
 * MAGENTO MASS IMPORTER CLASS
 *
 * version : 0.6
 * author : S.BRACQUEMONT aka dweeves
 * updated : 2010-10-09
 *
 */


require_once(dirname(__DIR__)."/inc/magmi_defs.php");
/* use external file for db helper */
require_once("magmi_engine.php");
require_once("magmi_valueparser.php");

/**
 *
 * Magmi Product Import engine class
 * This class handle product import
 * @author dweeves
 *
 */
class Ayalon_ProductImportEngine extends Magmi_ProductImportEngine
{

  private $db_settings;
  private $magento_path;

  //current import row
  private $_current_row;
  //option id cache for select/multiselect
  private $_optidcache = null;
  //current item ids
  private $_curitemids = array("sku"=>null);
  //default store list to impact
  private $_same;
  //current import profile
  private $_profile;

  public function connectToMagento()
  {
    // et database infos from properties
    if (!$this->_connected) {
      $conn = $this->getProp("DATABASE", "connectivity", "net");
      $debug = $this->getProp("DATABASE", "debug", false);
      $socket = $this->getProp("DATABASE", "unix_socket");
      if ($conn == 'localxml') {
        $baseDir = $this->getProp('MAGENTO', 'basedir');
        $xmlPath = $baseDir.'/app/etc/local.xml';
        if (!file_exists($xmlPath)) {
          throw new Exception("Cannot load xml from path '$xmlPath'");
        }
        $xml = new SimpleXMLElement(file_get_contents($xmlPath));
        $default_setup = $xml->global->resources->{$this->getProp('DATABASE', 'resource', 'default_setup')}->connection;
        $host = $default_setup->host;
        $dbname = $default_setup->dbname;
        $user = $default_setup->username;
        $pass = $default_setup->password;
        $port = $default_setup->port;
      } else {
        $host = $this->getProp("DATABASE", "host", "localhost");
        $dbname = $this->getProp("DATABASE", "dbname", "magento");
        $user = $this->getProp("DATABASE", "user");
        $pass = $this->getProp("DATABASE", "password");
        $port = $this->getProp("DATABASE", "port", "3306");

        // JMI
        // Provide possibility to override the db settings
        if(count($this->db_settings)){
          $host = $this->db_settings['host'];
          $dbname = $this->db_settings['dbname'];
          $user = $this->db_settings['user'];
          $pass = $this->db_settings['pass'];
        }

      }
      $this->initDb($host, $dbname, $user, $pass, $port, $socket, $conn, $debug);
      // suggested by pastanislas
      $this->_db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }
  }

  public function setDBParams($db_settings){
    $this->db_settings = $db_settings;
  }

  public function setMagentoPath($magento_path){
    $this->magento_path = $magento_path;
  }

  public function engineInit($params)
  {

    // Possibility to set magento path on init
    if(!empty($this->magento_path)){
      $mdh=new LocalMagentoDirHandler($this->magento_path);
    }
    else{
      $mdh=new LocalMagentoDirHandler(Magmi_Config::getInstance()->getMagentoDir());
    }

    //Muss am ende stehen
    parent::engineInit($params);

  }

  public function processDataSourceLine($item, $rstep, &$tstart, &$tdiff, &$lastdbtime, &$lastrec)
  {
    // counter
    $res = array("ok"=>0,"last"=>0);
    $canceled = false;
    $this->_current_row++;
    if ($this->_current_row % $rstep == 0) {
      $this->reportStats($this->_current_row, $tstart, $tdiff, $lastdbtime, $lastrec);
    }
    try {
      if (is_array($item) && count($item) > 0) {
        // import item
        $this->beginTransaction();
        $importedok = $this->importItem($item);
        if ($importedok) {
          $res["ok"] = true;
          // JMI
          // After an import provide the product id in the result
          $res["_product_id"]=$importedok;
          $this->commitTransaction();
        } else {
          $res["ok"] = false;
          $this->rollbackTransaction();
        }
      } else {
        $this->log("ERROR - RECORD #$this->_current_row - INVALID RECORD", "error");
      }
      // intermediary measurement
    } catch (Exception $e) {
      $this->rollbackTransaction();
      $res["ok"] = false;
      $this->logException($e, "ERROR ON RECORD #$this->_current_row");
      if ($e->getMessage() == "MAGMI_RUN_CANCELED") {
        $canceled = true;
      }
    }
    if ($this->isLastItem($item) || $canceled) {
      unset($item);
      $res["last"] = 1;
    }

    unset($item);
    $this->updateSkuStats($res);

    return $res;
  }

  /**
   * full import workflow for item
   *
   * @param array $item
   *            : attribute values for product indexed by attribute_code
   */
  public function importItem($item) {
    $this->handleIgnore($item);
    if (Magmi_StateManager::getState() == "canceled") {
      throw new Exception("MAGMI_RUN_CANCELED");
    }
    // first step

    if (!$this->callPlugins("itemprocessors", "processItemBeforeId", $item)) {
      return FALSE;
    }

    // check if sku has been reset
    if (!isset($item["sku"]) || trim($item["sku"]) == '') {
      $this->log('No sku info found for record #' . $this->_current_row, "error");
      return FALSE;
    }
    // handle "computed" ignored columns
    $this->handleIgnore($item);
    // get Item identifiers in magento
    $itemids = $this->getItemIds($item);

    // extract product id & attribute set id
    $pid = $itemids["pid"];
    $asid = $itemids["asid"];


    $isnew = FALSE;
    if (isset($pid) && $this->mode == "xcreate") {
      $this->log("skipping existing sku:{$item["sku"]} - xcreate mode set", "skip");
      return FALSE;
    }

    if (!isset($pid)) {
      if ($this->mode !== 'update') {
        if (!isset($asid)) {
          $this->log("cannot create product sku:{$item["sku"]}, no attribute_set defined", "error");
          return FALSE;
        }
        $pid = $this->createProduct($item, $asid);
        $this->_curitemids["pid"] = $pid;
        $isnew = TRUE;
      }
      else {
        // mode is update, do nothing
        $this->log("skipping unknown sku:{$item["sku"]} - update mode set", "skip");
        return FALSE;
      }
    }
    else {
      // only change attribute sets if disable option is OFF
      if ($this->getProp("GLOBAL", "noattsetupdate", "off") == "off") {
        // if attribute set name is given and changed
        // compared to attribute set in db -> change!
        if (isset($item['attribute_set'])) {
          $newAsId = $this->getAttributeSetId($item['attribute_set']);
          if (isset($newAsId) && $newAsId != $asid) {
            // attribute set changed!
            $item['attribute_set_id'] = $newAsId;
            $asid = $newAsId;
            $itemids['asid'] = $newAsId;
          }
        }
      }
      $this->updateProduct($item, $pid);
    }

    try {
      $basemeta = array("product_id" => $pid, "new" => $isnew, "same" => $this->_same, "asid" => $asid);
      $fullmeta = array_merge($basemeta, $itemids);

      if (!$this->callPlugins("itemprocessors", "preprocessItemAfterId", $item, $fullmeta)) {
        return FALSE;
      }

      if (!$this->callPlugins("itemprocessors", "processItemAfterId", $item, $fullmeta)) {
        return FALSE;
      }

      if (count($item) == 0) {
        return TRUE;
      }
      // handle "computed" ignored columns from afterImport
      $this->handleIgnore($item);

      if (!$this->checkstore($item, $pid, $isnew)) {
        $this->log("invalid store value, skipping item");
        return FALSE;
      }
      // if column list has been modified by callback, update attribute info cache.
      $this->initAttrInfos(array_keys($item));
      // create new ones
      $attrmap = $this->attrbytype;
      do {
        $attrmap = $this->createAttributes($pid, $item, $attrmap, $isnew, $itemids);
      } while (count($attrmap) > 0);

      if (!testempty($item, "category_ids") || (isset($item["category_reset"]) && $item["category_reset"] == 1)) {
        // assign categories
        $this->assignCategories($pid, $item);
      }

      // update websites if column is set
      if (isset($item["websites"]) || $isnew) {
        $this->updateWebSites($pid, $item);
      }

      //fix for multiple stock update
      //always update stock
      $this->updateStock($pid, $item, $isnew);

      $this->touchProduct($pid);
      // ok,we're done
      if (!$this->callPlugins("itemprocessors", "processItemAfterImport", $item, $fullmeta)) {
        return FALSE;
      }
    }
    catch (Exception $e) {
      $this->callPlugins(array("itemprocessors"), "processItemException", $item, array("exception" => $e));
      $this->logException($e);
      throw $e;
    }
    return TRUE;

    // JMI
    // Return PID to use it later
    // https://bitbucket.org/johnorourke/magmi-tweaks-public/commits/68204e9196a6a4b8d04567a5f953295db0b271bd#chg-magmi/integration/inc/productimport_datapump.php
    return $pid;
  }
}