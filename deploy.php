<?php

function deleteLockFile($lockFile)
{
    $result = unlink($lockFile);
    
    if (!$result) {
        throw new \Exception("Unable to delete the lock file. Deployment will be restricted until the lock file is removed.");
    }
}

$config = json_decode(file_get_contents(".config.json"));

if (!$config) {
    throw new \Exception("Unable to parse configuration file. Please ensure the `.config.json` configuration file exists and is valid JSON.");
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

$headers = getallheaders();

$webhookSource = null;
$webhookEvent = null;

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

$request = json_decode(http_get_request_body());

if (!$request) {
    throw new \Exception("Web hook request body was not present or is invalid JSON.");
}

$fullGitRef = "refs/heads/".$config->git->branch;

if ($request->ref != $fullGitRef) {
    throw new \Exception("This push was not for the configured branch, therefore it is being ignored. If this is incorrect, please check the branch defined in the configuration file.");
}

?>