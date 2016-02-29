<?php

require_once("magmi_productimportengine.php");
require_once("ayalon_productimportengine.php");

class Ayalon_ProductImport_DataPump {
  protected $_engine = NULL;
  protected $_params = array();
  protected $_logger = NULL;
  protected $_importcolumns = array();
  protected $_defaultvalues = array();
  protected $_stats;
  protected $_crow;
  protected $_rstep = 100;
  protected $_mdpatched = FALSE;


  public function __construct()
  {
    $this->_engine = new Ayalon_ProductImportEngine();
    $this->_engine->setBuiltinPluginClasses("*datasources",
      dirname(__FILE__) . DIRSEP . "magmi_datapumpdatasource.php::Magmi_DatapumpDS");

    $this->_stats["tstart"] = microtime(true);
    // differential
    $this->_stats["tdiff"] = $this->_stats["tstart"];
  }

  public function setReportingStep($rstep) {
    $this->_rstep = $rstep;
  }

  public function beginImportSession($profile, $mode, $logger = NULL, $db_params = NULL, $magento_path = NULL) {
    $this->_engine->setLogger($logger);

    if (count($db_params)) {
      $this->_engine->setDBParams($db_params);
    }

    if (!empty($magento_path)) {
      $this->_engine->setMagentoPath($magento_path);
    }

    $this->_engine->initialize();
    $this->_params = array("profile" => $profile, "mode" => $mode);

    //Dieser Parameter ist notwendig, damit die Bilder in die richtige Magentoinstallation gelangen
    $this->_params['IMG:magento_dir'] = $magento_path;

    $this->_engine->engineInit($this->_params);
    $this->_engine->initImport($this->_params);
    //intermediary report step
    $this->_engine->initDbqStats();
    $pstep = $this->_engine->getProp("GLOBAL", "step", 0.5);
    //read each line
    $this->_stats["lastrec"] = 0;
    $this->_stats["lastdbtime"] = 0;
    $this->crow = 0;

  }


  public function setDefaultValues($dv = array()) {
    $this->_defaultvalues = $dv;
  }


  public function ingest($item = array()) {
    $item = array_merge($this->_defaultvalues, $item);
    $diff = array_diff(array_keys($item), $this->_importcolumns);
    if (count($diff) > 0) {
      $this->_importcolumns = array_keys($item);
      //process columns
      $this->_engine->callPlugins("itemprocessors", "processColumnList", $this->_importcolumns);
      $this->_engine->initAttrInfos($this->_importcolumns);
    }
    $res = $this->_engine->processDataSourceLine($item,
      $this->_rstep,
      $this->_stats["tstart"],
      $this->_stats["tdiff"],
      $this->_stats["lastdbtime"],
      $_this->stats["lastrec"]);
    return $res;

  }

  public function endImportSession() {
    $this->_engine->reportStats($this->_engine->getCurrentRow(),
      $this->_stats["tstart"],
      $this->_stats["tdiff"],
      $this->_stats["lastdbtime"],
      $_this->stats["lastrec"]);
    $skustats = $this->_engine->getSkuStats();
    $this->_engine->log("Skus imported OK:" . $skustats["ok"] . "/" . $skustats["nsku"], "info");
    if ($skustats["ko"] > 0) {
      $this->_engine->log("Skus imported KO:" . $skustats["ko"] . "/" . $skustats["nsku"], "warning");
    }

    $this->_engine->exitImport();
  }

}