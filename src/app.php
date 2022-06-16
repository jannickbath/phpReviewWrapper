<?php 
// TODO Make this login a module for dynamic import
// TODO Better Folder structure -> project folder
session_start();

$username = $_POST["username"] ?? false;
$password = $_POST["password"] ?? false;
$forum = isset($_POST["forum-active"]) ?? false;
$deletePostList = $_POST["delete-post-list"] ?? false;
$_SESSION["loggedIn"] = ($_SESSION["loggedIn"] ?? false);
$_SESSION["username"] = ($_SESSION["username"] ?? false);

$wrongData = false;
$disabled = false;

//User Array 
$users = [
	'demouser' => ['password' => 'demopass', 'id' => 0, 'disabled' => false]
];

//Login
if (($username && $password)) {
	if (!$users[$username]["disabled"]) {
		if ($users[$username]["password"] == $password) {
			$_SESSION["loggedIn"] = true;
			$_SESSION["username"] = $username;
		}else {
			$wrongData = true;
		}
	}
	else {
		$disabled = true;
	}
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
	author TEXT NOT NULL DEFAULT ''
)");

// Save message-content to database
if (isset($_POST["message-content"])) {
	$arr = json_decode($_POST["message-content"]);
	foreach ($arr as $element => $info) {
		$db->exec("INSERT INTO messages (element, message, author) VALUES ('$element', '$info[0]', '$info[1]');");
	}
}

// Fetch Entries
function getEntries() {
	$db = new SQLite3("./database/messages.db");
	$entries = $db->query("SELECT * FROM messages");
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

$actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Test Website</title>
	<link rel="stylesheet" href="./styles/output.css">
	<link rel="stylesheet" href="./project/dist/output.min.css">
</head>
<body <?php if (!$_SESSION["loggedIn"]) {echo 'class="bg-sunset overflow-hidden"';} ?>>
	<?php if ($_SESSION["loggedIn"]): ?>
		<!-- TODO Dynamic File Import -->
		<?php include "./project/src/pages/homepage/parsed.php" ?>
	<?php else: ?>
		<!-- Login Form -->
		<div class="wrapper w-full h-full grid place-items-center">
			<div class="">
				<h1 class="text-red-500 text-3xl text-center mb-5">Login</h1>
				<form action="<?=$actual_link;?>" method="POST" name="loginForm" class="grid gap-y-2">
					<input type="text" name="username" class="border border-black h-8 rounded-md">
					<input type="password" name="password" id="" class="border border-black h-8 rounded-md">
					<button onclick="submit('loginForm')" class="p-1 rounded-md bg-slate-300 hover:bg-slate-400 w-fit px-3 ml-auto">Submit</button>
				</form>
				<?php if ($wrongData): ?>
					<!-- Alert Wrong Data-->
					<div class="bg-red-400 py-3 px-6 rounded-lg absolute bottom-0 right-0 alert slide-top transition-opacity">
						<p>Es ist etwas schiefgelaufen! Bitte überprüfen sie ihre Eingabe.</p>
						<button class="absolute top-0 right-2 close-button">
							x
						</button>
					</div>
				<?php endif; ?>
				<?php if ($disabled): ?>
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
	<?php endif;?>

	<?php if ($_SESSION["loggedIn"]): ?>
		<div class="wrapper flex fixed right-10 bottom-5 gap-2 items-center">
			<!-- Submit Button -->
			<form action="<?=$actual_link;?>" method="POST" id="submitForm" name="submitForm" class="w-fit h-full bg-green-400 rounded-md">
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
			<form action="<?=$actual_link;?>" method="POST" name="logoutForm" class="w-fit h-fit">
				<input type="text" name="logout" class="hidden">
				<button onclick="submit('logoutForm')" class="bg-red-200 p-2 rounded-md hover:bg-red-400 mx-auto">Logout</button>
			</form>
		</div>

		<!-- Forum-Button -->
		<form action="<?=$actual_link;?>" method="POST" class="bg-red-200 left-2 bottom-5 fixed rounded-md hover:bg-red-400">
			<button class="p-2 rounded-md forum-button" title="Open the forum" type="submit">
				<img src="./assets/icons/chat.png" class="h-5 w-5" alt="">
			</button>
			<input type="text" class="hidden" name="forum-active">
		</form>

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
		<?php if ($forum): ?>
			<div class="forum fixed left-1/2 top-1/2 translate-x-[-50%] translate-y-[-50%] bg-white z-[200] w-8/12 h-[600px] rounded-sm p-4 flex flex-col justify-between">
				<div class="flex-grow">
					<h2 class="w-fit mx-auto text-3xl mb-5">Aktuelle Hinweise</h2>

					<!-- Messages -->
					<ul class="grid gap-3 grid-cols-4 child:bg-slate-300 child:rounded-md child:p-2 child:relative child:transition-colors">
						<?php foreach (getEntries() as $item => $element): ?>
							<li>
								<h4 class="text-2xl"><?=$element["element"]?><span class="text-sm ml-2">(<?=$element["author"]?>)</span></h4>
								<p class="text-lg"><?=$element["message"]?></p>
								<span class="hidden" aria-hidden="true" id="post-id"><?=$element["id"]?></span>
								<button class="absolute top-0 right-0 py-1 px-2 delete-post" title="delete message">x</button>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="footer flex justify-end">
					<!-- Delete Confirm Button -->
					<form action="<?=$actual_link?>" method="POST">
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

		editActive = false;
		toggled = false;
		activeElement = "";
		itemList = {};
		icons = ["./assets/icons/edit.png", "./assets/icons/cross.png"];
		deleteQueue = [];

		document.querySelector(".edit-button").onclick = (event) => {
			editActive = !editActive;

			//toggle visibility of submit-button
			if (editActive) {
				document.querySelector(".submit-button").style.setProperty("display", "block", "important");
			}else {
				document.querySelector(".submit-button").style.setProperty("display", "none", "important");
			}

			//toggle icon src
			event.target.src = icons[+ editActive];

			//make every ce_element clickable
			const divs = document.querySelectorAll("div");
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
										itemList = {...itemList, [classname]: []}
									}
									document.querySelector(".message-wrapper").style.setProperty("display", "flex", "important");
									document.querySelector(".message-wrapper textarea").focus();
									document.querySelector(".message-wrapper textarea").select();
									activeElement = classname;
								}else {
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
						element.onmouseenter= () => {
							if (editActive) {
								element.style.setProperty("border", "3px solid yellow", "important");
							}
						}
						element.onmouseleave= () => {
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

			itemList[activeElement] = [messageBoxVal, '<?=$_SESSION["username"];?>'];
			messageWrapper.style.removeProperty("display", "none", "important");

			document.querySelector("#submitForm input").value = JSON.stringify(itemList);
			console.log(itemList);
		}

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
	</script>
</body>
</html>