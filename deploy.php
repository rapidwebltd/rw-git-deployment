<?php

$config = json_decode(file_get_contents(".config.json"));

if (!$config) {
    throw new \Exception("Unable to parse configuration file. Please ensure the `.config.json` configuration file exists and is valid JSON.");
}

if ($config->security->token=="replace_me_with_a_random_string") {
    throw new \Exception("The security token is currently set to the default value. Please change this in the configuration file.");
}

if (!trim($config->security->token)) {
    throw new \Exception("The security token is currently empty. Please change this in the configuration file.");
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
    throw new \Exception("Unable to identify source of webhook request. This deployment script accepts requests from GitHub and GitLab web hooks only.");
}

if ($webhookType == null) {
    throw new \Exception("Unable to identify type of webhook request. This deployment script accepts push webhook requests only.");
}

if ($webhookType != "push") {
    throw new \Exception("This does not appear to be a push webhook request. This deployment script accepts push webhook requests only.");
}


?>