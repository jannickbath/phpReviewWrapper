<?php
    // Activity Tracker (Ping-System)
    $data = json_decode(file_get_contents('php://input'), true);
    $time = time();

    $username = $_SESSION["username"];
    $currentPage = $_SESSION["newPage"];

    if (isset($_POST["navigate-page"])) {
        $db_users->exec("UPDATE users SET lastping=$time, online=1, page='$currentPage' WHERE username='$username'");
    }

    if (isset($data["active"])) {
        $clientWidth = $data["position"]["width"];
        $clientHeight = $data["position"]["height"];
        $scrolled = $data["position"]["scroll"];
        $db_users->exec("UPDATE users SET lastping=$time, online=1, page='$currentPage', position='$clientWidth, $clientHeight, $scrolled' WHERE username='$username'");
    }

    if (isset($_GET["activity"])) {
        $userOnlineStates = [];

        foreach ($users as $username => $data) {
            $userOnlineStates = [...$userOnlineStates, ["online" => $data["online"], "username" => $username, "position" => $data["position"], "id" => $data["id"], "page" => $data["page"]]];
        }

        $json = json_encode($userOnlineStates);
        print_r($json);

        //Prevents HTML from loading
        $showHTML = 0;
    }

    foreach ($users as $username => $data) {
        if ($data["online"]) {
            if ($time - $data["lastping"] > 5 && $data["lastping"] != 0) {
                $db_users->exec("UPDATE users SET online=0 WHERE username='$username'");
            }
        }
    }
?>