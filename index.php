<?php

    session_start();

    if ($_SERVER['REQUEST_METHOD'] === 'GET'){
        echo file_get_contents("./static/main.html");
    } else if ($_SERVER["REQUEST_METHOD"] === "POST"){
        $db = new mysqli("localhost", "root", "", "Dmytro");
        if (isset($_POST['isLogin'])) {
            $login = $_POST['login'] ?? '';
            $password = $_POST['password'] ?? '';
        
            if (empty($login) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Логін та пароль не можуть бути пустими']);
                exit;
            }
        
            // Подготовленный запрос для предотвращения SQL-инъекций
            $stmt = $db->prepare("SELECT id, pass FROM Users WHERE username = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Помилка створення запиту до БД']);
                exit;
            }
        
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $result = $stmt->get_result();
        
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
        
                // Сравнение пароля
                if (password_verify($password, $user['pass'])) {
                    $_SESSION['user_id'] = $user['id']; // Сохраняем user_id в сессии
                    echo json_encode(['status' => 'success', 'id' => $user['id']]);
                    exit;
                }
            }
        
            echo json_encode(['status' => 'error', 'message' => 'Невірний логін або пароль']);
            exit;
        } else if (isset($_POST['isRegister'])) {
            $login = $_POST['login'] ?? '';
            $password = $_POST['password'] ?? '';
        
            // Проверка входных данных
            if (empty($login) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Логін та пароль не можуть бути пустими']);
                exit;
            }
        
            // Хеширование пароля
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
            // Подготовленный запрос для вставки данных
            $stmt = $db->prepare("INSERT INTO Users (username, pass) VALUES (?, ?)");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Ошибка подготовки запроса']);
                exit;
            }
        
            $stmt->bind_param("ss", $login, $hashedPassword);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id; // Сохраняем ID нового пользователя в сессии
                echo json_encode(['status' => 'success', 'id' => $stmt->insert_id]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ошибка регистрации']);
            }
        
            exit;
        } else if(isset($_POST["isExit"])) {
            session_unset();
            session_destroy();
            echo json_encode(['status' => 'success']);
            exit;
        } else if (isset($_SESSION["user_id"])) {
            $user_id = $_SESSION["user_id"];
        
            if (isset($_POST["isSendMessage"])) {
                if (isset($_POST["chat_id"], $_POST["content"])) {
                    $chat_id = (int)$_POST["chat_id"];
                    $content = $db->real_escape_string($_POST["content"]);
            
                    // Проверяем, состоит ли пользователь в чате
                    $query = "SELECT * FROM UserInChat WHERE user_id = $user_id AND chat_id = $chat_id";
                    $result = $db->query($query);
            
                    if ($result->num_rows > 0) {
                        // Получаем ID последнего сообщения
                        $messageIdQuery = "SELECT MAX(message_id) AS last_id FROM Messages WHERE chat_id = $chat_id";
                        $messageIdResult = $db->query($messageIdQuery);
            
                        $lastId = 0; // Если сообщений нет
                        if ($messageIdResult->num_rows > 0) {
                            $row = $messageIdResult->fetch_assoc();
                            $lastId = isset($row['last_id']) ? (int)$row['last_id'] + 1 : 0;
                        }
            
                        // Вставляем новое сообщение с message_id
                        $insertQuery = "INSERT INTO Messages (message_id, chat_id, user_id, content) VALUES ($lastId, $chat_id, $user_id, '$content')";
                        $db->query($insertQuery);
                    }
                }
            } elseif (isset($_POST["isCreateChat"])) {
                if (isset($_POST["chat_name"])) {
                    $chat_name = $db->real_escape_string($_POST["chat_name"]);
                    $insertQuery = "INSERT INTO Chat (chat_owner, chat_name) VALUES ($user_id, '$chat_name')";
                    $db->query($insertQuery);
                    echo json_encode(['id' => $db->insert_id]);
                }
            } elseif (isset($_POST["isDeleteMessage"])) {
                if (isset($_POST["chat_id"], $_POST["message_id"])) {
                    $chat_id = (int)$_POST["chat_id"];
                    $message_id = (int)$_POST["message_id"];
        
                    $query = "SELECT * FROM UserInChat WHERE user_id = $user_id AND chat_id = $chat_id";
                    $result = $db->query($query);
        
                    if ($result->num_rows > 0) {
                        $ownerQuery = "SELECT chat_owner FROM Chat WHERE id = $chat_id";
                        $ownerResult = $db->query($ownerQuery);
                        $ownerRow = $ownerResult->fetch_assoc();
        
                        $messageQuery = "SELECT * FROM Messages WHERE message_id = $message_id AND chat_id = $chat_id";
                        $messageResult = $db->query($messageQuery);
                        $messageRow = $messageResult->fetch_assoc();
        
                        if ($messageRow && ($messageRow["user_id"] == $user_id || $ownerRow["chat_owner"] == $user_id)) {
                            $deleteQuery = "DELETE FROM Messages WHERE message_id = $message_id";
                            $db->query($deleteQuery);
                        }
                    }
                }
            } elseif (isset($_POST["isAddToChat"])) {
                if (isset($_POST["user_id"], $_POST["chat_id"])) {
                    $target_user_id = (int)$_POST["user_id"];
                    $chat_id = (int)$_POST["chat_id"];
        
                    $userQuery = "SELECT * FROM Users WHERE id = $target_user_id";
                    $chatQuery = "SELECT * FROM Chat WHERE id = $chat_id";
                    if ($db->query($userQuery)->num_rows > 0 && $db->query($chatQuery)->num_rows > 0) {
                        $insertQuery = "INSERT INTO UserInChat (user_id, chat_id) VALUES ($target_user_id, $chat_id)";
                        $db->query($insertQuery);
                    }
                }
            } elseif (isset($_POST["isKickFromChat"])) {
                if (isset($_POST["user_id"], $_POST["chat_id"])) {
                    $target_user_id = (int)$_POST["user_id"];
                    $chat_id = (int)$_POST["chat_id"];
        
                    $chatQuery = "SELECT * FROM Chat WHERE id = $chat_id AND chat_owner = $user_id";
                    if ($db->query($chatQuery)->num_rows > 0) {
                        $deleteQuery = "DELETE FROM UserInChat WHERE user_id = $target_user_id AND chat_id = $chat_id";
                        $db->query($deleteQuery);
                    }
                }
            } elseif (isset($_POST["isEditProfile"])) {
                if (isset($_POST["avatar"], $_POST["username"])) {
                    $avatar_id = (int)$_POST["avatar"];
                    $username = $db->real_escape_string($_POST["username"]);
        
                    $fileQuery = "SELECT * FROM Files WHERE id = $avatar_id";
                    $avatar_id = $db->query($fileQuery)->num_rows > 0 ? $avatar_id : 0;
        
                    $updateQuery = "UPDATE Users SET avatar = $avatar_id, username = '$username' WHERE id = $user_id";
                    $db->query($updateQuery);
                }
            } elseif (isset($_POST["isGetChats"])) {
                $query = "SELECT * FROM Chat";
                $result = $db->query($query);
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            } elseif (isset($_POST["isGetMessages"])) {
                if (isset($_POST["chat_id"])) {
                    $chat_id = (int)$_POST["chat_id"];
        
                    $query = "SELECT * FROM Messages WHERE chat_id = $chat_id";
                    $result = $db->query($query);
                    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
                }
            } elseif (isset($_POST["isGetUserData"])) {
                if (isset($_POST["user_id"])) {
                    $target_user_id = (int)$_POST["user_id"];
                    $query = "SELECT id, isBot, username FROM Users WHERE id = $target_user_id";
                } elseif (isset($_POST["username"])) {
                    $username = $db->real_escape_string($_POST["username"]);
                    $query = "SELECT id, isBot, username FROM Users WHERE username = '$username'";
                }
                $result = $db->query($query);
                echo json_encode($result->fetch_assoc());
            } elseif (isset($_POST["isSearchUserByUsername"])) {
                if (isset($_POST["username"])) {
                    $username = $db->real_escape_string($_POST["username"]);
                    $query = "SELECT id, username, avatar, isBot FROM Users WHERE username LIKE '%$username%'";
                    $result = $db->query($query);
                    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
                }
            }
        }
    }

?>