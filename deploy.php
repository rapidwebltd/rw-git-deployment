<?php

function deleteLockFile($lockFile)
{
    $result = unlink($lockFile);

    if (!$result) {
        throw new \Exception("Unable to delete the lock file. Deployment will be restricted until the lock file is removed.");
    }
}

function exceptionHandler($exception) {
    $exceptionFileName = "/tmp/deploymentException_".time().".txt";
    file_put_contents($exceptionFileName, print_r($exception, true));
}

set_exception_handler('exceptionHandler');

$config = json_decode(file_get_contents("config/.config.json"));

if (!$config) {
    throw new \Exception("Unable to parse configuration file. Please ensure the `config/.config.json` configuration file exists and is valid JSON.");
}

if ($config->security->token=="replace_me_with_a_random_string") {
    throw new \Exception("The security token is currently set to the default value. Please change this in the configuration file.");
}

if (!trim($config->security->token)) {
    throw new \Exception("The security token specified in the configuration file is currently blank. Please change this in the configuration file.");
}

if (!isset($_GET['st'])) {
    throw new \Exception("No `st` parameter in URL. This is required.");
}

if ($_GET['st']!=$config->security->token) {
    throw new \Exception("Security token is invalid. Change your request to match the security token in the configuration file.");
}

if (!trim($config->files->lock)) {
    throw new \Exception("The lock file specified in the configuration file is currenty blank. Please change this in the configuration file.");
}

if (file_exists($config->files->lock)) {
    throw new \Exception("The lock file currently exists, suggesting the script is currently running. Deployment will be restricted until the lock file is removed.");
}

$result = file_put_contents($config->files->lock, time());

if (!$result) {
    throw new Exception("There was an error writing to the lock file. Check the user running this script has write permissions on the containing directory.");
}

$webhookSource = null;
$webhookEvent = null;

if (isset($config->security->checkWebhookHeaders) && $config->security->checkWebhookHeaders) {

    $headers = apache_request_headers();

    foreach($headers as $name => $value) {

        if ($name == "X-Github-Event"){

            $webhookSource = "GitHub";

            if ($value == "push")
            {
                $webhookType = "push";
            }
        }
        elseif ($name == "X-Gitlab-Event")
        {
            $webhookSource == "GitLab";

            if ($value == "Push Hook")
            {
                $webhookType == "push";
            }
        }
    }

    if ($webhookSource == null) {
        deleteLockFile($config->files->lock);
        throw new \Exception("Unable to identify source of webhook request. This deployment script accepts requests from GitHub and GitLab web hooks only.");
    }

    if ($webhookType == null) {
        deleteLockFile($config->files->lock);
        throw new \Exception("Unable to identify type of webhook request. This deployment script accepts push webhook requests only.");
    }

    if ($webhookType != "push") {
        deleteLockFile($config->files->lock);
        throw new \Exception("This does not appear to be a push webhook request. This deployment script accepts push webhook requests only.");
    }
}

$requestBody = file_get_contents('php://input');

$request = json_decode($requestBody);

if (!$request) {
    deleteLockFile($config->files->lock);
    throw new \Exception("Web hook request body was not present or is invalid JSON.");
}

if (!trim($config->git->branch)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The git branch specified in the configuration file is currenty blank. Please change this in the configuration file.");
}

$fullGitRef = "refs/heads/".$config->git->branch;

if ($request->ref != $fullGitRef) {
    deleteLockFile($config->files->lock);
    throw new \Exception("This push was not for the configured branch, therefore it is being ignored. If this is incorrect, please check the branch defined in the configuration file.");
}

if (!trim($config->git->repositoryUrl)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The git repository url specified in the configuration file is currenty blank. Please change this in the configuration file.");
}

if (!trim($config->directories->temporary)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The temporary directory specified in the configuration file is currenty blank. Please change this in the configuration file.");
}

$commands = array();

if (!is_dir($config->directories->temporary)) {

    if (!mkdir($config->directories->temporary)) {
        deleteLockFile($config->files->lock);
        throw new \Exception("The temporary directory could not be created. Check the user running this script has write permissions on the containing directory.");
    }

    $commands[] = sprintf("git clone --depth=1 --branch %s %s %s", $config->git->branch, $config->git->repositoryUrl, $config->directories->temporary);
}
else
{
    $commands[] = sprintf('git --git-dir="%s.git" --work-tree="%s" fetch origin %s', $config->directories->temporary, $config->directories->temporary, $config->git->branch);
	$commands[] = sprintf('git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD', $config->directories->temporary, $config->directories->temporary);
}

if (!chdir($config->directories->temporary)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The current directory could not be changed to the specified temporary directory.");
}

if ($config->git->updateSubmodules) {
    $commands[] = "git submodule update --init --recursive";
}

if (isset($config->composer)) {
  if (isset($config->composer->install) && $config->composer->install) {
    $commands[] = sprintf('composer install');
  }
  if (isset($config->composer->update) && $config->composer->update) {
    $commands[] = sprintf('composer update');
  }
}

if (isset($config->npm)) {
  if (isset($config->npm->install) && $config->npm->install) {
    $npmInstallCommand = "npm install";
    if (isset($config->npm->packages) && count($config->npm->modules)) {
      foreach($config->npm->packages as $package) {
        $npmInstallCommand = " ".$package;
      }
    }
    $commands[] = sprintf($npmInstallCommand);
  }
}

if (isset($config->gulp)) {
  if (isset($config->gulp->tasks) && count($config->gulp->tasks)) {
    foreach($config->gulp->tasks as $gulpTask) {
      $commands[] = sprintf('gulp %s', $gulpTask);
    }
  }
}

if (!trim($config->directories->deployment)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The deployment directory specified in the configuration file is currenty blank. Please change this in the configuration file.");
}

if (!is_dir($config->directories->deployment)) {
    deleteLockFile($config->files->lock);
    throw new \Exception("The deployment directory does not seem to exist. Please create the directory or check the directory specified in the configuration file..");
}

$commands[] = sprintf('rsync -rltDzvO %s %s', $config->directories->temporary, $config->directories->deployment);

if (isset($config->permissions) && count($config->permissions)) {
  foreach($config->permissions as $permission) {
    $commands[] = sprintf('chmod %s %s %s', $permission->octal, $permission->path, $permission->recursive ? '-R' : '');
  }
}

foreach ($commands as $command) {

	set_time_limit(60*5);

	$output = array();

	exec($command." 2>&1", $output, $returnCode);

	if ($returnCode !== 0) {
	    deleteLockFile($config->files->lock);
		throw new \Exception("Deployment error - Return code ".$returnCode." received when attempting to run command: ".$command." - ".print_r($output, true));
	}
}

deleteLockFile($config->files->lock);

?>
