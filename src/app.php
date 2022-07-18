<?php
session_start();

$username = $_POST["username"] ?? false;
$password = $_POST["password"] ?? false;
$forum = isset($_POST["forum-active"]) ?? false;
$seiten = isset($_POST["seiten-active"]) ?? false;
$userManagement= isset($_POST["user-management"]) ?? false;
$page_creation_active = isset($_POST["page-creation-active"]) ?? false;
$recompile = $_POST["recompile"] ?? null;
$deletePage = $_POST["delete-page"] ?? null;
$deletePostList = $_POST["delete-post-list"] ?? false;
$_SESSION["loggedIn"] = ($_SESSION["loggedIn"] ?? false);
$_SESSION["username"] = ($_SESSION["username"] ?? false);

$_SESSION["newPage"] = ($_SESSION["newPage"] ?? null);

// Login-Specific Variables 
$wrongData = false;
$disabled = false;
$devMode = false;

// Role Management
$removeRole = $_POST["remove-role"] ?? null;
$addRole = $_POST["add-role"] ?? null;
$user_id = $_POST["user_id"] ?? null;

// User Management
$addUser = $_POST["add-user"] ?? null;
$addDeveloper = isset($_POST["add-developer"]) ?? false;
$deleteUser = $_POST["delete-user"] ?? null;

// Project Info
$pInfoJSON = file_get_contents("./project_info.json");
$pInfo = json_decode($pInfoJSON, true);
$ticketUrl = $pInfo["ticket"];
$designUrl = $pInfo["design"];
$designUrl_mobile = $pInfo["design-mobile"];

// Database (Users)
$db_users = new SQLite3("./database/users.db");
$db_users->exec("CREATE TABLE IF NOT EXISTS users(
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	username TEXT NOT NULL DEFAULT '',
	password TEXT NOT NULL DEFAULT '',
	disabled BOOLEAN NOT NULL DEFAULT '',
	developer BOOLEAN NOT NULL DEFAULT ''
)");

// Time Tracking
function convertSeconds($seconds) {
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    $minutes = $dtF->diff($dtT)->format('%i') / 60;
	return	$dtF->diff($dtT)->format('%h') + $minutes;
}

$_SESSION["time_start"] = ($_SESSION["time_start"] ?? time());
if (isset($_POST["time_stop"])) {
	$usedTime = time() - $_SESSION["time_start"];
	$hoursUsed = convertSeconds($usedTime);

	//Weiterleitung
	echo "<script>window.location.href = '${ticketUrl}/time_entries/new?time_entry[hours]=${hoursUsed}&time_entry[comments]=Testing%20Live%20Seite&time_entry[activity_id]=12'</script>";

	//Reset Timer
	$_SESSION["time_start"] = time();
}

// Fetch Users
$entries = $db_users->query("SELECT * FROM users");
$users = [];

while ($row = $entries->fetchArray()) {
	$users[$row["username"]] = ["password" => $row["password"], "disabled" => $row["disabled"], "developer" => $row["developer"], "id" => $row["id"]];
}

// Role-Management
if (isset($removeRole) && isset($user_id)) {
	$db_users->exec("UPDATE users SET $removeRole=0 WHERE id=$user_id");
}

if (isset($addRole) && isset($user_id)) {
	$db_users->exec("UPDATE users SET $addRole=1 WHERE id=$user_id");
}

// User-Management
if (isset($deleteUser)) {
	$db_users->exec("DELETE FROM users WHERE id=$deleteUser");
}

if (isset($addUser)) {
	$db_users->exec("INSERT INTO users (username, developer, password) VALUES ('$addUser', '$addDeveloper', 'demopass')");
}

// Fetch Pages
$basePath = "./project/src/pages";
$pages = array_diff(scandir($basePath), array(".", ".."));
$pageList = [];
$paths = [];

foreach ($pages as $page) {
	$files = array_diff(scandir($basePath . "/" . $page), array(".", ".."));
	foreach ($files as $file) {
		$fullPath = join("/", [$basePath, $page, $file]);
		array_push($paths, $fullPath);
	}
	array_push($pageList, $paths);
	$paths = [];
}

// Page Generator
$filename = $_POST["pagename"] ?? null;
$compList = $_POST["comps"] ?? null;
$compList = explode(",", $compList);

$compPath = "./project/src/components";
$pagePath = "./project/src/pages";
$stylesheet = "../../../dist/output.css";
$javascript = "../../../dist/main.min.js";

$components = array_diff(scandir($compPath), array(".", ".."));

$codeList = [];

$boilerplateHTML_start = "
<!DOCTYPE html>
<html lang=\"en\">
<head>
	<meta charset=\"UTF-8\">
	<link rel=\"stylesheet\" href=\"${stylesheet}\">
	<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
	<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
	<title>${filename}</title>
</head>
<body>
";

$boilerplateHTML_end = "
<script src=\"${javascript}\"></script>
</body>
</html>
";

$boilerplatePHP_end = "
<script src=\"${javascript}\"></script>
";

// $recompile contains the basename of the page thats getting recompiled
if (isset($filename) && isset($compList) || isset($recompile)) {
	if (isset($recompile)) {
		$info = json_decode(file_get_contents("${pagePath}/${recompile}/info.json"), true);
		$compList = $info["components"];
		$filename = $recompile;
	}

	foreach ($compList as $component) {
		if (in_array($component, $components)) {
			preg_match("/(?<=<!-- start -->)(.*)(?=<!-- end -->)/s", file_get_contents($compPath . "/" . $component . "/" . "${component}.html"), $match);
			$codeList = [...$codeList, $match[0]];
		}
	}
	mkdir("${pagePath}/${filename}");

	//Write to files
	$generatedPage = fopen("${pagePath}/${filename}/${filename}.html", "w");
	$generatedPage_php = fopen("${pagePath}/${filename}/${filename}.php", "w");
	$infoJSON = fopen("${pagePath}/${filename}/info.json", "w");

	fwrite($generatedPage, $boilerplateHTML_start);
	foreach ($codeList as $code) {
		fwrite($generatedPage, $code);
		fwrite($generatedPage_php, $code);
	}
	fwrite($generatedPage, $boilerplateHTML_end);
	fwrite($generatedPage_php, $boilerplatePHP_end);

	fclose($generatedPage);
	fclose($generatedPage_php);

	$info = json_encode([
		"date" => date("d.m.Y H:i:s"),
		"user" => $_SESSION["username"],
		"components" => $compList
	]);

	fwrite($infoJSON, $info);
	fclose($infoJSON);

	// Change Paths
	$newContent = preg_replace('~((\.\.\/){3})+~', "./project/", file_get_contents("${pagePath}/${filename}/${filename}.php"));
	$phpFile = fopen("${pagePath}/${filename}/${filename}.php", "w");
	fwrite($phpFile, $newContent);
	fclose($phpFile);
}

if (isset($deletePage) && isset($pagePath)) {
	system("rm -rf ".escapeshellarg("${pagePath}/${deletePage}"));
}

//Login
if (($username && $password)) {
	if (!$users[$username]["disabled"]) {
		if ($users[$username]["password"] == $password) {
			$_SESSION["loggedIn"] = true;
			$_SESSION["username"] = $username;
		} else {
			$wrongData = true;
		}
	} else {
		$disabled = true;
	}

}

//Keeps variables while navigating
if ($_SESSION["loggedIn"]) {
	if ($users[$_SESSION["username"]]["developer"]) {
		$devMode = true;
	}
}

// Page
if (isset($_POST["navigate-page"])) {
	$_SESSION["newPage"] = $_POST["navigate-page"];
}

//Logout
if (isset($_POST["logout"])) {
	$_SESSION["loggedIn"] = false;
}

//Database (Messages)
$db = new SQLite3("./database/messages.db");
$db->exec("CREATE TABLE IF NOT EXISTS messages(
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	element TEXT NOT NULL DEFAULT '',
	message TEXT NOT NULL DEFAULT '',
	author TEXT NOT NULL DEFAULT '',
	page TEXT NOT NULL DEFAULT ''
)");


// Save message-content to database (messages)
if (isset($_POST["message-content"])) {
	$arr = json_decode($_POST["message-content"]);
	$newPage = $_SESSION["newPage"];
	foreach ($arr as $element => $info) {
		$db->exec("INSERT INTO messages (element, message, author, page) VALUES ('$element', '$info[0]', '$info[1]', '$newPage');");
	}
}

// Fetch Entries (messages)
function getEntries()
{
	$currentPage = $_SESSION["newPage"];
	$db = new SQLite3("./database/messages.db");
	$entries = $db->query("SELECT * FROM messages WHERE page='$currentPage'");
	$messages = [];

	while ($row = $entries->fetchArray()) {
		array_push($messages, ["element" => $row["element"], "message" => $row["message"], "author" => $row["author"], "id" => $row["id"]]);
	}
	return $messages;
}

// Delete Posts
if ($deletePostList) {
	$data = explode(",", $deletePostList);
	foreach ($data as $postId) {
		$db->exec("DELETE FROM messages WHERE id=$postId");
	}
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Review - Raumaustatter</title>
	<link rel="icon" type="image/png" href="./assets/images/phpReviewWrapper_icon.png" sizes="96x96">
	<link rel="stylesheet" href="./styles/output.css">
	<link rel="stylesheet" href="./project/dist/output.min.css">
</head>

<body <?php if (!$_SESSION["loggedIn"]) {
			echo 'class="bg-sunset overflow-hidden"';
		} ?>>
	<?php if ($_SESSION["loggedIn"]) : ?>
		<?php if (isset($_SESSION["newPage"])) : ?>
			<!-- TODO Dynamic File Import -->
			<?php include $_SESSION["newPage"]?>
		<?php else : ?>
			<?php include "./project/src/pages/homepage/parsed.php";?>
		<?php endif; ?>
	<?php else : ?>
		<!-- Login Form -->
		<div class="wrapper w-full h-full grid place-items-center">
			<div class="">
				<h1 class="text-red-500 text-3xl text-center mb-5">Login</h1>
				<form action="./" method="POST" name="loginForm" class="grid gap-y-2">
					<input type="text" name="username" class="border border-black h-8 rounded-md">
					<input type="password" name="password" id="" class="border border-black h-8 rounded-md">
					<button onclick="submit('loginForm')" class="p-1 rounded-md bg-slate-300 hover:bg-slate-400 w-fit px-3 ml-auto">Submit</button>
				</form>
				<?php if ($wrongData) : ?>
					<!-- Alert Wrong Data-->
					<div class="bg-red-400 py-3 px-6 rounded-lg absolute bottom-0 right-0 alert slide-top transition-opacity">
						<p>Es ist etwas schiefgelaufen! Bitte überprüfen sie ihre Eingabe.</p>
						<button class="absolute top-0 right-2 close-button">
							x
						</button>
					</div>
				<?php endif; ?>
				<?php if ($disabled) : ?>
					<!-- Alert Wrong Data-->
					<div class="bg-orange-400 py-3 px-6 rounded-lg absolute bottom-0 right-0 alert slide-top transition-opacity">
						<p>Ihr Benutzer ist derzeit deaktiviert! Bitte wenden sie sich an einen Administrator.</p>
						<button class="absolute top-0 right-2 close-button">
							x
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($_SESSION["loggedIn"]) : ?>
		<div class="wrapper flex fixed right-10 bottom-5 gap-2 items-center z-[99999]">
			<!-- Submit Button -->
			<form action="./" method="POST" id="submitForm" name="submitForm" class="w-fit h-full bg-green-400 rounded-md">
				<input type="text" class="hidden" name="message-content">
				<button class="bg-green-400 hover:bg-green-600 p-2 rounded-md submit-button hidden" type="submit" title="submit">
					<img src="./assets/icons/submit.png" class="h-5 w-5" alt="">
				</button>
			</form>

			<!-- Edit Button -->
			<button class="bg-red-200 hover:bg-red-400 p-2 rounded-md edit-button h-fit" title="Add a Description to a component">
				<img src="./assets/icons/edit.png" class="h-5 w-5" alt="">
			</button>

			<?php if ($designUrl != "") : ?>
				<!-- Design -->
				<div class="flex flex-row-reverse group gap-2">
					<a class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto" href="<?=$designUrl?>" rel="noopener noreferrer" target="_blank">Design</a>
					<a class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto group-hover:block hidden" href="<?=$designUrl_mobile?>" rel="noopener noreferrer" target="_blank" class="">Design - Mobile</a>
				</div>
			<?php endif; ?>

			<?php if ($ticketUrl != "") : ?>
				<!-- Ticket -->
				<div class="flex flex-row-reverse group gap-2">
					<a class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto" href="<?=$ticketUrl?>" rel="noopener noreferrer" target="_blank" class="">Ticket</a>

					<!-- Zeit buchen -->
					<form action="./" method="POST" class="w-fit h-fit hidden group-hover:block">
						<input type="text" name="time_stop" class="hidden">
						<button class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto">Zeit buchen</button>
					</form>
				</div>

			<?php endif; ?>

			<!-- Log out -->
			<form action="./" method="POST" name="logoutForm" class="w-fit h-fit">
				<input type="text" name="logout" class="hidden">
				<button onclick="submit('logoutForm')" class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto">Logout</button>
			</form>
		</div>

		<div class="fixed bottom-5 left-2 flex gap-2 z-[99999]">
			<!-- Seitenübersicht-Button -->
			<form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="Open the Page Overview" type="submit">
					<img src="./assets/icons/pages.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="seiten-active">
			</form>

			<!-- Forum-Button -->
			<form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="Open the forum" type="submit">
					<img src="./assets/icons/chat.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="forum-active">
			</form>

			<?php if ($devMode) : ?> 
			<!-- Developer Settings -->
			<form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="Developer Settings" type="submit">
					<img src="./assets/icons/settings.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="page-creation-active">
			</form>

			<!-- User Management -->
			<form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="User Management" type="submit">
					<img src="./assets/icons/user.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="user-management">
			</form>
			<?php endif; ?>

			<!-- Time Management -->
			<!-- <form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="Time Management" type="submit">
					<img src="./assets/icons/clock.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="time_stop">
			</form> -->
		</div>

		<!-- Message Box -->
		<div class="message-wrapper fixed bg-slate-500 top-1/2 left-1/2 translate-x-[-50%] translate-y-[-50%] w-[500px] h-[250px] rounded-md justify-center hidden text-lg z-[150]">
			<div class="w-10/12 h-1/2 text-center mt-4">
				<h3 class="mb-2 text-2xl">Was ist ihnen aufgefallen?</h3>
				<textarea id="message-bug" class="w-full h-full rounded-sm"></textarea>
				<div class="flex-grow flex justify-end">
					<button id="submit-message" class="px-2 py-1 bg-blue-300 rounded-md">Submit</button>
				</div>
			</div>
		</div>
		<!-- Written Message -->
		<div class="written-message hidden w-fit translate-y-[-100px] bg-white px-4 py-2 absolute z-[150]">
			<h5></h5>
			<p></p>
		</div>

		<!-- Forum -->
		<?php if ($forum) : ?>
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col justify-between">
				<div class="flex-grow">
					<h2 class="w-fit mx-auto text-3xl mb-5">Aktuelle Hinweise</h2>

					<!-- Messages -->
					<ul class="grid gap-3 grid-cols-3 child:bg-slate-300 child-hover:bg-slate-400 child:rounded-md child:p-2 child:relative child:transition-colors child-hover:shadow-md">
						<?php foreach (getEntries() as $item => $element) : ?>
							<li title="Navigate to element" class="form-element">
								<a href="#<?= $element['element'] ?>">
									<div class="flex items-center gap-2">
										<h4 class="text-2xl"><?= $element["element"] ?></h4>
										<span class="text-sm">(<?= $element["author"] ?>)</span>
									</div>
									<p class="text-lg"><?= $element["message"] ?></p>
									<span class="hidden" aria-hidden="true" id="post-id"><?= $element["id"] ?></span>
								</a>
								<button class="absolute top-0 right-0 py-1 px-2 delete-post" title="delete message">x</button>
							</li>
						<?php endforeach; ?>
						<?php if (empty(getEntries())): ?>
							<h3 class="pointer-events-none">Derzeit sind keine Einträge vorhanden!</h3>
						<?php endif; ?>
					</ul>
				</div>

				<div class="footer flex justify-end">
					<!-- Delete Confirm Button -->
					<form action="./" method="POST">
						<button class="bg-red-200 text-red-500 font-bold rounded-md px-3 py-2 hover:bg-red-500 hover:text-red-200 transition-colors hidden" id="confirm-delete">Delete Posts</button>
						<input type="text" name="delete-post-list" id="delete-post-list" class="hidden">
					</form>
				</div>

				<!-- Close Form (forces page-refresh) -->
				<form action="" method="POST">
					<button id="close-forum" class="absolute right-3 top-3 w-fit" type="submit">
						<img src="./assets/icons/cross.png" alt="" class="w-5 h-5">
					</button>
				</form>
			</div>
		<?php endif; ?>

		<!-- Seitenübersicht -->
		<?php if ($seiten): ?>
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col justify-between">
				<div class="flex-grow">
					<h2 class="w-fit mx-auto text-3xl mb-5">Seitenübersicht</h2>

					<ul class="flex gap-5 child:grid child:gap-2 child:flex-grow flex-wrap">
						<?php foreach ($pageList as $page) : ?>
							<?php 
							$phpFile = array_values(array_filter($page, function($value) {
								return pathinfo($value, PATHINFO_EXTENSION) == "php";
							}))[0];
							$htmlFile = array_values(array_filter($page, function($value) {
								return pathinfo($value, PATHINFO_EXTENSION) == "html";
							}))[0];
							?>
							<li>
								<h3 class="title text-center text-2xl font-bold"><?=basename($htmlFile, ".html");?></h3>
								<iframe src="<?=$htmlFile?>" frameborder="0" class="mx-auto w-full"></iframe>
								<div class="flex items-center justify-between">
									<form action="./" method="POST">
										<input type="text" class="hidden" value="<?=$phpFile?>" name="navigate-page">
										<button type="submit" class="text-center text-blue-500 hover:text-blue-300">Navigieren</button>
									</form>
									<?php if ($devMode) : ?>
									<div class="flex gap-3">
										<!-- Recompile-Button -->
										<form action="./" method="POST">
											<input type="text" name="recompile" class="hidden" value="<?=basename($htmlFile, ".html")?>">
											<input type="text" name="seiten-active" class="hidden">
											<button title="recompile page" type="submit">
												<img src="./assets/icons/reload.png" alt="" class="w-5 h-5">
											</button>
										</form>
										<!-- Delete-Button -->
										<form action="./" method="POST">
											<input type="text" name="delete-page" class="hidden" value="<?=basename($htmlFile, ".html")?>">
											<button title="delete page" type="submit">
												<img src="./assets/icons/delete.png" alt="" class="w-5 h-5">
											</button>
										</form>
									</div>	
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<!-- Close Form (forces page-refresh) -->
				<form action="" method="POST">
					<button id="close-forum" class="absolute right-3 top-3 w-fit" type="submit">
						<img src="./assets/icons/cross.png" alt="" class="w-5 h-5">
					</button>
				</form>
			</div>
		<?php endif; ?>

		<!-- Page Creation Tool -->
		<?php if ($page_creation_active): ?>
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col">
				<h2 class="mx-auto text-3xl text-blue-400">Page Generation Tool</h2>
				<div class="">
					<form action="./" method="POST">
						<div class="select">
							<label for="comp-selection">Choose a Component</label>
							<select name="comp-selection" id="comp-selection">
								<?php foreach($components as $component) : ?>
									<option value="<?=$component?>"><?=$component?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<button type="button" class="bg-lime-700 px-4 py-1 text-white rounded-md" id="add-component">Add +</button>


						<!-- Submit Button -->
						<div class="absolute bottom-5 right-5 flex gap-2">
							<input type="text" class="border-2 rounded-sm" placeholder="Pagename" name="pagename" required>
							<button type="submit" class="bg-lime-500 px-4 py-1 rounded-md border-2 border-lime-500 hover:bg-transparent transition-colors">Generate</button>
							<input type="text" class="hidden" id="comp-transmitter" name="comps">
						</div>
					</form>
				</div>

				<div class="mx-auto p-5 flex flex-wrap">
					<ul id="page-structure" class="child:p-2 child:bg-violet-400 child:rounded-md flex gap-12 child:h-fit child:after:content-['→'] child:after:absolute child:after:-right-10 child:after:top-1/2 child:after:-translate-y-[50%] child:after:text-2xl child:relative last:child:after:hidden child:text-center flex-wrap">
						<!-- Content Managed by JS -->
					</ul>
				</div>


				<!-- Close Form (forces page-refresh) -->
				<form action="" method="POST">
					<button id="close-forum" class="absolute right-3 top-3 w-fit" type="submit">
						<img src="./assets/icons/cross.png" alt="" class="w-5 h-5">
					</button>
				</form>
			</div>
		<?php endif; ?>

		<?php if ($userManagement): ?>
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col justify-between">
				<div class="flex-grow">
					<h2 class="w-fit mx-auto text-3xl mb-5">User-Management</h2>

					<ul class="child:flex grid gap-3 child:gap-2 child:py-2 child:items-center child:relative">
						<?php foreach ($users as $username => $detail) : ?>
							<li>
								<img src="./assets/icons/user.png" alt="" class="w-7 h-7">
								<h4><?=$username?> --> </h4>
								<form action="./" method="POST" class="flex gap-5">
									<input type="text" class="hidden" name="user_id" value="<?=$detail['id']?>">

									<!-- Developer -->
									<div class="flex items-center gap-2">
										<p>Developer:</p>
										<?php if ($detail["developer"]) : ?>
											<input type="text" name="remove-role" value="developer" class="hidden">
											<?php if ($username != $_SESSION["username"]) : ?>
												<button type="submit" class="bg-red-500 px-4 py-1 rounded-md" title="Remove developer role">Remove</button>
											<?php else : ?>
												<button type="submit" class="bg-red-500 px-4 py-1 rounded-md disabled" title="Remove developer role">Remove</button>
											<?php endif; ?>
										<?php else : ?>
											<input type="text" name="add-role" value="developer" class="hidden">
											<button type="submit" class="bg-green-500 px-4 py-1 rounded-md" title="Add developer role">Add</button>
										<?php endif; ?>
									</div>
								</form>

								<?php if ($username != $_SESSION["username"]) : ?>
								<div class="button-section absolute right-0 flex gap-2">
									<form action="./" method="POST">
										<input type="text" class="hidden" name="user_id" value="<?=$detail['id']?>">

										<!-- Disabled -->
										<div class="flex items-center gap-2">
											<?php if ($detail["disabled"]) : ?>
												<input type="text" name="remove-role" value="disabled" class="hidden">
												<button type="submit" title="Unlock User">
													<img src="./assets/icons/locked.png" alt="" class="w-7 h-7">
												</button>
											<?php else : ?>
												<input type="text" name="add-role" value="disabled" class="hidden">
												<button type="submit" title="Lock User">
													<img src="./assets/icons/unlocked.png" alt="" class="w-7 h-7">
												</button>
											<?php endif; ?>
										</div>
									</form>

									<!-- Delete-User-Button -->
									<form action="./" method="POST">
										<input type="text" class="hidden" name="delete-user" value="<?=$detail['id']?>">
										<button type="submit" title="Delete User">
											<img src="./assets/icons/delete.png" alt="" class="w-7 h-7">
										</button>
									</form>
								</div>
								<?php else : ?>
								<div class="user-info absolute right-0 bg-blue-300 h-full p-2 grid place-items-center font-bold rounded-lg">
									You
								</div>
								<?php endif; ?>

							</li>
						<?php endforeach; ?>
					</ul>

					<!-- Adding Users -->
					<div class="user-adding-wrapper absolute bottom-2 left-2">
						<form action="./" method="POST" class="flex gap-3">
							<input type="text" placeholder="Username" name="add-user" class="border-2 rounded-sm">
							<div class="flex items-center gap-2">
								<p>Roles: </p>
								<ul>
									<li>
										<input type="checkbox" name="add-developer">
										<label for="add-developer">Developer</label>
									</li>
								</ul>
							</div>
							<button type="submit" class="bg-green-400 rounded-md px-2 py-1">Add</button>
						</form>
					</div>
				</div>

				<!-- Close Form (forces page-refresh) -->
				<form action="" method="POST">
					<button id="close-forum" class="absolute right-3 top-3 w-fit" type="submit">
						<img src="./assets/icons/cross.png" alt="" class="w-5 h-5">
					</button>
				</form>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<script>
		function submit(formName) {
			document.forms[formName].submit();
		}

		document.querySelectorAll(".close-button").forEach((element) => {
			element.onclick = (event) => {
				event.target.parentElement.style.opacity = 0;
			}
		});

		var editActive = false;
		var toggled = false;
		var activeElement = "";
		var itemList = {};
		var deleteQueue = [];
		const icons = ["./assets/icons/edit.png", "./assets/icons/cross.png"];
		const divs = document.querySelectorAll("div");

		document.querySelector(".edit-button").onclick = (event) => {
			editActive = !editActive;

			//toggle visibility of submit-button
			if (editActive) {
				document.querySelector(".submit-button").style.setProperty("display", "block", "important");
			} else {
				document.querySelector(".submit-button").style.setProperty("display", "none", "important");
			}

			//toggle icon src
			document.querySelector(".edit-button img").src = icons[+editActive];

			//make every ce_element clickable
			divs.forEach((element) => {
				element.classList.forEach((classname) => {
					if (classname.startsWith("ce_")) {
						element.onclick = () => {
							if (editActive) {
								toggled = !toggled;
								if (toggled) {
									// element.style.setProperty("background-color", "red", "important");
									element.classList.add("red-indicator");
									if (!(classname in itemList)) {
										itemList = {
											...itemList,
											[classname]: []
										}
									}
									document.querySelector(".message-wrapper").style.setProperty("display", "flex", "important");
									document.querySelector(".message-wrapper textarea").focus();
									document.querySelector(".message-wrapper textarea").select();
									activeElement = classname;
								} else {
									// element.style.removeProperty("background-color");
									element.classList.remove("red-indicator");
									element.classList.remove("blue-indicator");
									if (classname in itemList) {
										delete itemList[classname];
									}
									document.querySelector(".message-wrapper").style.setProperty("display", "none", "important");
								}
								document.querySelector("#submitForm input").value = JSON.stringify(itemList);
								console.log(itemList);
							}
						}

						// Shows edited elements
						if (classname in itemList && element.nextElementSibling.className.split(" ")[0] != "written-message") {
							// element.style.setProperty("background-color", "skyblue", "important");
							element.classList.add("blue-indicator");
							element.classList.remove("red-indicator");
							var writtenMessage = document.querySelector(".written-message").cloneNode(true);
							writtenMessage.style.setProperty("display", "block", "important");

							writtenMessage.querySelector("p").innerText = itemList[classname][0];
							writtenMessage.querySelector("h5").innerText = `${itemList[classname][1]}'s Message:`;
							element.parentNode.insertBefore(writtenMessage, element.nextSibling);
						}

						// Converts cursor to cell-cursor
						if (editActive) {
							element.style.setProperty("cursor", "cell");
						}

						// border-indicator on hover
						element.onmouseenter = () => {
							if (editActive) {
								element.style.setProperty("border", "3px solid yellow", "important");
							}
						}
						element.onmouseleave = () => {
							if (editActive) {
								element.style.removeProperty("border");
							}
						}

					}
				});
			});
		}
		// Submit Message
		document.getElementById("submit-message").onclick = (e) => {
			const messageBoxVal = document.getElementById("message-bug").value;
			const messageWrapper = document.querySelector(".message-wrapper");

			itemList[activeElement] = [messageBoxVal, '<?= $_SESSION["username"]; ?>'];
			messageWrapper.style.removeProperty("display", "none", "important");

			document.querySelector("#submitForm input").value = JSON.stringify(itemList);
			console.log(itemList);
		}

		//Adds selected posts to delete queue
		document.querySelectorAll(".delete-post").forEach(button => {
			button.onclick = (e) => {
				const id = e.target.parentElement.querySelector("span.hidden").innerText;
				if (!deleteQueue.includes(id)) {
					deleteQueue.push(id);
				}
				e.target.parentElement.style.setProperty("background-color", "red", "important");

				if (deleteQueue.length > 0) {
					document.getElementById("confirm-delete").style.setProperty("display", "block", "important");
				}
				// console.log(deleteQueue);
				document.getElementById("delete-post-list").value = deleteQueue;
			}
		});

		// Add Id's for each content element (for navigation) -> see forum
		divs.forEach((element) => {
			element.classList.forEach((classname) => {
				if (classname.startsWith("ce_")) {
					if (!document.getElementById(classname) ?? false) {
						element.setAttribute("id", classname);
					}
				}
			});
		});

		// Adding Components
		const compSelection = document.querySelector("#comp-selection");
		const compTransmitter = document.querySelector("#comp-transmitter");
		const pageStructure = document.querySelector("#page-structure");
		let comps = [];

		document.querySelector("#add-component").onclick = () => {
			comps = [...comps, compSelection.value];
			compTransmitter.value = comps;

			let node = document.createElement("li");
			node.innerText = compSelection.value;
			pageStructure.appendChild(node);
		}

	</script>
</body>

</html>