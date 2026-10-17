import Bugsnag from '@bugsnag/js';

const bugsnagApiKey = document.querySelector('meta[name="bugsnag-api-key"]')?.content;
const bugsnagAppVersion = document.querySelector('meta[name="bugsnag-app-version"]')?.content;

if (bugsnagApiKey) {
    Bugsnag.start({
        apiKey: bugsnagApiKey,
        appVersion: bugsnagAppVersion || undefined,
        autoTrackSessions: false,
        collectUserIp: false,
        redactedKeys: [
            /^password$/i,
            /^token$/i,
            /^state$/i,
        ],
    });
}
