<?php 

namespace Leobard\DeployYourself;

/**
 * deploying a kirby from git
 */
class DeployYourself {
  
  /**
   * the verbosity this should use, inspired by PSR3
   */
  const LOG_VERBOSITY_LEVELS = [
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug', 
  ];
  
  /**
   * key in config.php 
   */
  const CONFIG_KEY = 'leobard.deploy-yourself';
  
  /**
   * an ISO8601 like format that is ok-ish for filenames
   */
  const ISODATETIME_FILENAME = 'Y-m-d\THisO';
  
  /**
   * maintenance filename
   * see https://github.com/moritzebeling/kirby-maintenance
   */
  const MAINTENANCE_FILE = '.maintenance';
  
  /**
   * array, the one inside deploy-yourself with the parameters
   */
  protected ?array $config = null;
  
  /**
   * name of the logfile where this will write into
   */
  protected ?string $logfilename = null;
  
  /**
   * logfile of this run
   */
  protected mixed $logfilehandle = null;
  
  public function __construct(
    /**
     * path to the /site/config folder
     */
    protected String $kirby_root_config_path,
    /**
     * the URL parameters passed via $_GET to the hook
     */
    protected array $get_parameters
  ) {}
  
  /**
   * read the config value and return it
   */
  function config(string $name) : mixed {
    if (null === $this->config) {
      // start with the default configuration
      $this->config = [
        // where the .git folder of the installation is
        'gitdir' => '.git',
        // commands to run after git pull
        'post_pull_cmds' => [],
        // dry run does not run commands but writes the commands into the log folder and file
        'dryrun' => false,
        // where to store log files
        'logfolder' => 'site/logs/',
        // pattern of log files
        'logfilename' => 'deploy-yourself-{ISODATETIME}.log',
        // how many logfiles to keep
        'logretain' => 10,
        // see LOG_VERBOSITY_LEVELS
        'logverbosity' => 'notice',
        // git binary
        'gitbinary' => 'git',
        // header token, by default null. If set, the hook will only work if header present
        'token' => null,
        // http header where the token is expected in
        'header_token' => 'X-deploy-yourself-token',
        // enable maintenance mode by placing .maintenance into the kirby root
        // see https://github.com/moritzebeling/kirby-maintenance
        'maintenancemode' => false,
      ];
      
      $thisthis = $this;
      $mergeConfig = function (string $filename) use ($thisthis) {
        if (file_exists($filename)) {
          $load = include($filename);
          if (isset($load[self::CONFIG_KEY]))
            $thisthis->config = array_merge($thisthis->config, $load[self::CONFIG_KEY]);
        }
      };
      // load the standard kirby config for this plugins config
      $mergeConfig($this->kirby_root_config_path . DIRECTORY_SEPARATOR . 'config.php');
      // load an optional override config with the current hostname for this plugins config
      $mergeConfig($this->kirby_root_config_path . DIRECTORY_SEPARATOR . 'config.'.$_SERVER['SERVER_NAME'].'.php');
    }
    return $this->config[$name] ?? null;
  }
  
  /**
   * This is the hook that is called from outside
   */
  function hook() {
    $this->log_start();
    try {
      $this->token_verify();
      /**
       * first part of pull, the network connection and fetch. This can be done before maintenance mode starts
       */
      $r = $this->run_command_git('fetch');
      if (0 != $r) {
        $this->log('fetch not successful, aborting further commands');
        return;
      }
      $this->maintenance_start();
      try {
        /**
         * optional: if ?reset=hard is passed to overwrite local changes...
         */
        if ('hard' == ($this->get_parameters['reset'] ?? null)) {
          $r = $this->run_command_git('reset --hard');
          if (0 != $r) {
            $this->log('reset --hard not successful, aborting further commands');
            return;
          }            
        }
            
        /**
         * THIS is the important line of this plugin
         */
        $r = $this->run_command_git('merge');
        if ($r != 0) {
          $this->log('merge not successful, aborting further commands');
          return;
        }
        $this->run_post_pull_cmds();
        echo "ok, logfile in ".$this->logfilename;
      } finally {
        $this->maintenance_end();
      }
      $this->log_notice("end of hook: successful");
    } catch (\Exception $x) {
      $this->log('critical', $x);
      echo "fail";
    } finally {
      $this->log_end();
    }
    exit();
  }
  
  function log_start() {
    if (!is_dir($this->config('logfolder'))) {
      if (mkdir($this->config('logfolder'), 0777, true) !== true) {
        throw new Exception('Cannot create logdir '.$this->config('logfolder'));
      }
    }
    $isodatetime=gmdate(self::ISODATETIME_FILENAME);
    $this->logfilename = $this->config('logfolder') . str_replace('{ISODATETIME}', $isodatetime, $this->config('logfilename'));
    if (!($this->logfilehandle = fopen($this->logfilename, "w"))) {
      throw new Exception('Cannot open logfile '.$logfilename);
    }
    $this->log('notice', 'Starting log, verbosity '.$this->config('logverbosity'));
    if ($this->log_is('debug')) {
      $this->log('debug', 'configuration: '.var_export($this->config, true));
    }
  }
  
  function log_notice(string $message, array $context = array()) {
    $this->log('notice', $message, $context);
  }
  
  function log_is($level) {
    $levelConfigured = array_search($this->config('logverbosity'), self::LOG_VERBOSITY_LEVELS);
    $levelPassed = array_search($level, self::LOG_VERBOSITY_LEVELS);
    return ( $levelPassed <= $levelConfigured );
  }
  
  function log($level, $message, array $context = array()) {
    if (!$this->log_is($level))
      return;
    if ($message instanceof \Exception) {
      $message = 'Exception: ' . $message->getMessage() . "\n" . $message->getCode();
    }
    fwrite($this->logfilehandle, $message . "\n");
  }
  
  function log_end() {
    fclose($this->logfilehandle); 
    $logretain = $this->config('logretain');
    if (is_int($logretain)  && $logretain >= 1) {
      // retain only the number of files defined
      $files = glob($this->config('logfolder') . str_replace('{ISODATETIME}', '*', $this->config('logfilename')));
      while (count($files) > $logretain) {
        $deletefilename = array_shift($files);
        
        if (unlink($deletefilename) === false) {
          trigger_error('cannot delete outdated logfile '.$deletefilename);
          break;
        } 
      }
    }
  }
  
  function maintenance_start() {
    if (! $this->config('maintenancemode'))
      return;
    if (file_put_contents(self::MAINTENANCE_FILE, 'site updating') === false) {
      $this->log('error', 'cannot write '.self::MAINTENANCE_FILE);
    } else {
      $this->log_notice('maintenance_start');
    }
  }
  
  function maintenance_end() {
    if (! $this->config('maintenancemode'))
      return;
    if (unlink(self::MAINTENANCE_FILE) === false) {
      $this->log('error', 'cannot delete '.self::MAINTENANCE_FILE);
    } else {
      $this->log_notice('maintenance_end');
    }
  }
  
  function run_command(string $cmd) : int {
    $output = null;
    $retval = null;
    $this->log_notice("cmd: ".$cmd);
    if ($this->config('dryrun')) {
      $output = ['dryrun'];
      $retval = 0;
    } else {
      // pipe stderr into stdout on linux
      $cmd_to_exec = $cmd.' 2>&1';
      exec($cmd_to_exec, $output, $retval);
    }
    $this->log_notice("ret: ".$retval);
    $outputstring = "Output:\n";
    // somehow that didn't do it...
    // htmlspecialchars(implode("<br/>\n", $output));
    foreach ($output as $l) {
      $outputstring .= $l . "\n";
    }
    $this->log_notice($outputstring);
    return $retval;
  }
  
  function run_command_git(string $gitcmd) : int {
    if ($this->config('gitdir') != '.git') {
      $gitcmd = ' --git-dir ' . $this->config('gitdir') . ' ' . $gitcmd;
    }
    return $this->run_command($this->config('gitbinary').' '.$gitcmd);
  }
  
  function run_post_pull_cmds() {
    $post_pull_cmds = $this->config('post_pull_cmds');
    if (count($post_pull_cmds) > 0) {
      $this->log('info', 'run_post_pull_cmds');
      foreach ($post_pull_cmds as $cmd) {
        $r = $this->run_command($cmd);
        if ($r != 0) {
          $this->log('warning', 'last cmd failed, aborting post_pull_cmds');
          break;
        }
      }
    }
  }
  
  /**
   * if the token is required, verify it
   * if it fails, throw an exception
   */
  function token_verify() {
    if ($requiredtoken = $this->config('token')) {
      $headername = 'HTTP_'.str_replace('-', '_', strtoupper($this->config('header_token')));
      $passedtoken = $_SERVER[$headername] ?? null;
      if ($requiredtoken != $passedtoken) {
        throw new \Exception('token required, wrong token passed: "'.$passedtoken.'"');
      }
    }
  }
  
  
  function update() {
    /*
            'git --git-dir ../web_.git fetch origin 2>&1',
            'git --git-dir ../web_.git reset --hard origin/dev 2>&1',
            //'git --git-dir ../web_.git reset --hard HEAD 2>&1',
            //'git --git-dir ../web_.git merge \'@{u}\' 2>&1',
     */
  }

}