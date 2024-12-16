var r = document.querySelector('*')
r.style.setProperty("--registration", "0")
r.style.setProperty("--hideLogin", "0")

const loginForm = $("#login-form")[0]
const registrForm = $("#registr-form")[0]

let timer = null;

var current_user_id = 1;

// registrForm.inert = true



$("#switch-to-login").on("click", () => {
    r.style.setProperty("--registration", "0")
    loginForm.inert = false
    registrForm.inert = true
})

$("#switch-to-registration").on("click", () => {
    r.style.setProperty("--registration", "-1")
    loginForm.inert = true
    registrForm.inert = false
})

$("#login-btn").on("click", () => {login()})
$("#registration-btn").on("click", () => {registration()})

function hideLogin() {
    r.style.setProperty("--hideLogin", "1")
}

function login(){
    $.post(".", {
        isLogin: true,
        login: $("#log-login").val(),
        password: $("#log-pass").val()
    },(data) => {
        data = JSON.parse(data)
        if(data["error"]){
            alert(data["error"])
        } else {
            current_user_id = data["id"]
            loadChats();
            hideLogin();
            startHellLoop();
        }
    })
}

function registration(){
    $.post(".", {
        isRegister: true,
        login: $("#reg-login").val(),
        password: $("#reg-pass").val()
    },(data) => {
        data = JSON.parse(data)
        if(data["error"]){
            alert(data["error"])
        } else {
            current_user_id = data["id"]
            loadChats();
            hideLogin();
            startHellLoop();
        }
    })
}

document.addEventListener('keydown', (e) => {
    if(e.code == "Enter"){
        if(!registrForm.inert) {
            registration()
        } else {
            login()
        }
    }
});

let currentChatId = -1;
let cachedChats = null;
let cachedMessages = {};
let cachedUsers = {};

// Загружает чаты и отображает их в списке
function loadChats() {
    $.post(".", { isGetChats: true }, function (chats) {
        cachedChats = JSON.parse(chats);
        renderChats();
    });
}

function startHellLoop(){
    timer = setInterval(() => {
        loadChats();
        if(currentChatId != -1){
            loadChat(currentChatId)
        }
    }, 3000)
}


function renderChats() {
    const chatHolder = $("#chat-holder");
    chatHolder.empty();

    cachedChats.forEach((chat) => {
        const chatElement = $(
            `<div class="list-el unselectable" id="chat-${chat.id}" data-chat-id="${chat.id}">
              <div class="chat-ico unselectable mod${chat.id%10}" id="ico-list-${chat.id}">${chat.chat_name[0]}</div>
              <div class="chat-name-lastmsg">
                <div class="chat-list-name" id="cln-${chat.id}">${chat.chat_name}</div>
                <div class="chat-last-message" id="clm-${chat.id}">Загрузка...</div>
              </div>
            </div>`
        );
    
        chatHolder.append(chatElement);
    
          // Добавляем обработчик клика по чату
        chatElement.click(function () {
            $(".list-el").removeClass("selected");
            $(this).addClass("selected");
            console.log("dfjgkfgjdfg", chat.id)
            currentChatId = chat.id
        });
    
        loadLastMessage(chat.id);
    });
}

function loadLastMessage(chatId) {
    $.post(".", { isGetMessages: true, chat_id: chatId }, function (messages) {
        cachedMessages[chatId] = JSON.parse(messages);
        const lastMessage = cachedMessages[chatId].slice(-1)[0];

        if (lastMessage) {
            loadUserData(lastMessage.user_id, function (user) {
            $(`#clm-${chatId}`).text(`${user.username}: ${lastMessage.content}`);
            });
        } else {
            $(`#clm-${chatId}`).text("Нет сообщений");
        }
    });
}

function loadUserData(userId, callback) {
    if (cachedUsers[userId]) {
        callback(cachedUsers[userId]);
        return;
    }

    $.post(".", { isGetUserData: true, user_id: userId }, function (user) {
        cachedUsers[userId] = JSON.parse(user);
        callback(cachedUsers[userId]);
    });
}

// Создание чата
$("#add-chat").click(function () {
    const randomName = Math.random().toString(36).substring(2, 10);
    $.post(".", { isCreateChat: true, chat_name: randomName }, function (chatId) {
        var data = JSON.parse(chatId)
        console.log(data);

        // Добавляем создателя в созданный чат
        $.post(".", { isAddToChat: true, user_id: current_user_id, chat_id: data.id }, function () {
            cachedChats = null; // Сбросить кеш
            loadChats();
        });
    });
});

// Отображение сообщений в чате
function loadChat(chatId) {
    const chat = cachedChats.find((c) => c.id === chatId);
    $("#inaera-logo").text(chat.chat_name[0]);
    $("#chat-name").text(chat.chat_name);

    const messageArea = $("#message-aera");
    messageArea.empty();

    cachedMessages[currentChatId].forEach((message, i) => {
        loadUserData(message.user_id, function (user) {
            var addClass = ""
            var dispAva = "none"
            if(cachedMessages[currentChatId].length == 1) {addClass = "message-segment-solo"; dispAva = "block"}
            else if(i == 0 ) addClass = "message-segment-top"
            else if(i == cachedMessages[currentChatId].length - 1 && cachedMessages[currentChatId][i-1].user_id == message.user_id) {addClass = "message-segment-end"; dispAva = "block"}
            else if(i == cachedMessages[currentChatId].length - 1) {addClass = "message-segment-solo"; dispAva = "block"}
            else if(cachedMessages[currentChatId][i-1].user_id == message.user_id && cachedMessages[currentChatId][i+1].user_id == message.user_id) addClass = "message-segment-mid"
            else if(cachedMessages[currentChatId][i-1].user_id == message.user_id) {addClass = "message-segment-end"; dispAva = "block"}
            else if(cachedMessages[currentChatId][i+1].user_id == message.user_id) addClass = "message-segment-top"
            else {addClass = "message-segment-solo"; dispAva = "block"}

            const messageElement = $(
                `<div class="message-avatar">
                <div class="chat-ico unselectable in-chat-avatar mod${message.user_id%10}" style="display:${dispAva};">${user.username[0]}</div>
                </div>
                <div class="block-aera message ${addClass} ${ message.user_id == current_user_id ? 'message-by-me' : ''}">
                ${message.content}
                </div>`
            );

            messageArea.append(messageElement);
        });
    });
}

// Отправка сообщения
$("#send-message").click(function () {
    const messageText = $("#new-message-text").val();

    if (messageText.trim() !== "") {
        $.post(".", { isSendMessage: true, chat_id: currentChatId, content: messageText }, function () {
            $("#new-message-text").val("");
            cachedMessages[currentChatId] = null; // Сбросить кеш сообщений
            loadLastMessage(currentChatId);
        });
    }
});

// Добавление пользователя
$("#new-message-text").on("input", function () {
    const inputText = $(this).val();
    if (inputText.startsWith("/adduser ")) {
        const username = inputText.substring(9);

        $.post(".", { isGetUserData: true, username: username }, function (user) {
            const parsedUser = JSON.parse(user);

            if (parsedUser) {
                $.post(".", { isAddToChat: true, user_id: parsedUser.id, chat_id: currentChatId }, function () {
                    alert(`Пользователь ${parsedUser.username} добавлен в чат.`);
                });
            } else {
                alert("Пользователь не найден.");
            }
        });
    }
});

// Начальная загрузка
