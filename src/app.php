<?php
// TODO Make this login a module for dynamic import
// TODO Better Folder structure -> project folder
session_start();

$username = $_POST["username"] ?? false;
$password = $_POST["password"] ?? false;
$forum = isset($_POST["forum-active"]) ?? false;
$seiten = isset($_POST["seiten-active"]) ?? false;
$page_creation_active = isset($_POST["page-creation-active"]) ?? false;
$deletePostList = $_POST["delete-post-list"] ?? false;
$_SESSION["loggedIn"] = ($_SESSION["loggedIn"] ?? false);
$_SESSION["username"] = ($_SESSION["username"] ?? false);

$_SESSION["newPage"] = ($_SESSION["newPage"] ?? null);

$wrongData = false;
$disabled = false;
$devMode = false;

//User Array 
$users = [
	'demouser' => ['password' => 'demopass', 'id' => 0, 'disabled' => false, 'developer' => true]
];

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
$filename = "generatedPage";
$compList = ["navbar", "text", "contact_inline"];

$compPath = "./project/src/components";
$pagePath = "./project/src/pages";
$stylesheet = "../../../dist/output.css";
$javascript = "../../../dist/main.min.js";

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


$components = array_diff(scandir($compPath), array(".", ".."));
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

fwrite($generatedPage, $boilerplateHTML_start);
foreach ($codeList as $code) {
	fwrite($generatedPage, $code);
	fwrite($generatedPage_php, $code);
}
fwrite($generatedPage, $boilerplateHTML_end);

fclose($generatedPage);
fclose($generatedPage_php);


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

//Database
$db = new SQLite3("./database/messages.db");
$db->exec("CREATE TABLE IF NOT EXISTS messages(
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	element TEXT NOT NULL DEFAULT '',
	message TEXT NOT NULL DEFAULT '',
	author TEXT NOT NULL DEFAULT '',
	page TEXT NOT NULL DEFAULT ''
)");

// Save message-content to database
if (isset($_POST["message-content"])) {
	$arr = json_decode($_POST["message-content"]);
	$newPage = $_SESSION["newPage"];
	foreach ($arr as $element => $info) {
		$db->exec("INSERT INTO messages (element, message, author, page) VALUES ('$element', '$info[0]', '$info[1]', '$newPage');");
	}
}

// Fetch Entries
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
	<title>Test Website</title>
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
		<div class="wrapper flex fixed right-10 bottom-5 gap-2 items-center">
			<!-- Submit Button -->
			<form action="./" method="POST" id="submitForm" name="submitForm" class="w-fit h-full bg-green-400 rounded-md">
				<input type="text" class="hidden" name="message-content">
				<button class="bg-green-400 hover:bg-green-600 p-2 rounded-md submit-button hidden" type="submit" title="submit">
					<img src="./assets/icons/submit.png" class="h-5 w-5" alt="">
				</button>
			</form>
			<!-- Edit Button -->
			<button class="bg-red-200 hover:bg-red-400 p-2 rounded-md edit-button group" title="Add a Description to a component">
				<img src="./assets/icons/edit.png" class="h-5 w-5" alt="">
			</button>
			<!-- Log out -->
			<form action="./" method="POST" name="logoutForm" class="w-fit h-fit">
				<input type="text" name="logout" class="hidden">
				<button onclick="submit('logoutForm')" class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto">Logout</button>
			</form>
		</div>

		<div class="fixed bottom-5 left-2 flex gap-2">
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

			<!-- Developer Settings -->
			<?php if ($devMode) : ?> 
			<form action="./" method="POST" class="bg-red-200 rounded-md hover:bg-red-400 z-10">
				<button class="p-2 rounded-md forum-button" title="Developer Settings" type="submit">
					<img src="./assets/icons/settings.png" class="h-5 w-5" alt="">
				</button>
				<input type="text" class="hidden" name="page-creation-active">
			</form>
			<?php endif; ?>
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

					<ul class="flex gap-2 child:grid child:gap-2 child:flex-grow flex-wrap">
						<?php foreach ($pageList as $page) : ?>
							<li>
								<h3 class="title text-center text-2xl font-bold"><?=basename($page[0], ".html");?></h3>
								<iframe src="<?=$page[0]?>" frameborder="0" class="mx-auto w-full"></iframe>
								<form action="./" method="POST">
									<input type="text" class="hidden" value="<?=$page[1]?>" name="navigate-page">
									<button type="submit" class="text-center text-blue-500 hover:text-blue-300">Navigieren</button>
								</form>
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
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col justify-between">
				<h2>Hello World</h2>

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
			event.target.src = icons[+editActive];

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

	</script>
</body>

</html>