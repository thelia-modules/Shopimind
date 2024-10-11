self.addEventListener('push', function (event) {
    if (! (self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    function sendNotification(data) {
        var title = data.title;
        var body = data.body;
        var icon = data.icon;
        var image = data.image;
        var url = data.link;

        /**
         * check the following link for more push options:
         *      https://web-push-book.gauntface.com/chapter-05/02-display-a-notification/#visual-options
         */
        return self.registration.showNotification(title, {
            body: body,
            icon: icon,
            image: image,
            data: {
                url: url
            }
        });
    }

    if (event.data) {
        var notification = JSON.parse(event.data.text());

        event.waitUntil(sendNotification(notification));
    }
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    if (clients.openWindow && event.notification.data.url) {
        event.waitUntil(clients.openWindow(event.notification.data.url));
    }
});
