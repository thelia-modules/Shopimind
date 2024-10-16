<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\PassiveSynchronization;

require_once \dirname(__DIR__).'/vendor-module/autoload.php';

use Shopimind\Data\NewsletterSubscribersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmNewsletterSubscribers;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\NewsletterQuery;

class SyncNewsletterSubscribers
{
    /**
     * Process synchronization for newsletter subscribers.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncNewsletterSubscribers(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $newsletterSubscriberIds = null;
        if (!empty($ids)) {
            $newsletterSubscriberIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($newsletterSubscriberIds)) {
                $count = NewsletterQuery::create()->find()->count();
            } else {
                $count = NewsletterQuery::create()->filterById($newsletterSubscriberIds)->find()->count();
            }
        } else {
            if (empty($newsletterSubscriberIds)) {
                $count = NewsletterQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = NewsletterQuery::create()->filterById($newsletterSubscriberIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
            }
        }

        if ($count == 0) {
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset($synchronizationStatus['synchronization_status']['newsletter_subscribers'])
            && $synchronizationStatus['synchronization_status']['newsletter_subscribers'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('newsletter_subscribers', 1);

        Utils::launchSynchronisation('newsletter-subscribers', $lastUpdate, $newsletterSubscriberIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes newsletter subscribers.
     *
     * @param Request $request
     * @return void
     */
    public static function syncNewsletterSubscribers(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $newsletterSubscriberIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $newsletterSubscriberIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 1;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($newsletterSubscriberIds)) {
                        $newsletters = NewsletterQuery::create()->offset($offset)->limit($limit)->find();
                    } else {
                        $newsletters = NewsletterQuery::create()->filterById($newsletterSubscriberIds)->offset($offset)->limit($limit)->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($newsletterSubscriberIds)) {
                        $newsletters = NewsletterQuery::create()->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $newsletters = NewsletterQuery::create()->offset($offset)->filterById($newsletterSubscriberIds)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($newsletters->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($newsletters->count() > 0) {
                    $data = [];
                    foreach ($newsletters as $newsletter) {
                        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber($newsletter);
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmNewsletterSubscribers::bulkSave(Utils::getAuth($requestHeaders), $data);

                    Utils::handleResponse($response);

                    Utils::log('newsletterSubscribers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('newsletterSubscribers', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('newsletterSubscribers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('newsletter_subscribers', 0);
        }

        Utils::updateSynchronizationStatus('newsletter_subscribers', 0);
    }
}
