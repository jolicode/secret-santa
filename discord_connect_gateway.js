/*
 * This code was used to make our Discord application connect to a gateway at
 * least once, as required by Discord before being able to send any message to
 * a channel.
 *
 * See https://discordapp.com/developers/docs/resources/channel#create-message
 *
 * npm install ws dotenv
 */

var WebSocket = require('ws');
require('dotenv').config();

const socket = new WebSocket('wss://gateway.discord.gg/?v=6&encoding=json');

let lastSequenceReceived = null;
let isNeedingIdentification = true;

const sendHeartBeat = () => {
    sendMessage(lastSequenceReceived, 1);
};

const sendIdentification = () => {
    sendMessage({
        "token": process.env.DISCORD_BOT_TOKEN,
        "properties": {
            "$os": "linux",
            "$browser": "disco",
            "$device": "disco"
        },
        "large_threshold": 250,
    }, 2);
};

const sendMessage = (data, op) => {
    socket.send(JSON.stringify({
        op: op,
        d: data,
    }));
};

socket.on('message', function(message) {
    message = JSON.parse(message);

    if (message.op === 10 && message.d.heartbeat_interval) {
        lastSequenceReceived = message.s;
        setTimeout(sendHeartBeat, message.d.heartbeat_interval);
    }

    if (message.op === 11) {
        // heart beat ack

        if (isNeedingIdentification) {
            isNeedingIdentification = false;
            setInterval(sendIdentification, 1000);
        }
    }
});

socket.on('close', function(code) {
    console.log('Disconnected: ' + code);
});

socket.on('error', function(error) {
    console.log('Error: ' + error.code);
});
