<?php

namespace App\Controller\API;

use App\Core\DB;
use PDO;
use Exception;
use Dotenv\Dotenv;

class PublicOpsController
{
    private $pdo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $this->pdo = DB::connection();
    }

    public function fetchEvent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        try {

            $sql = "
                SELECT
                    e.*,
                    COUNT(eg.id) AS photos,
                    GROUP_CONCAT(
                        eg.image
                        ORDER BY eg.id ASC
                        SEPARATOR '|||'
                    ) AS gallery_images
                FROM events e
                LEFT JOIN event_gallery eg
                    ON e.event_id = eg.event_id
                GROUP BY e.id
                ORDER BY e.event_date ASC, e.event_time ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedEvents = array_map(function ($event) {

                $eventDate = new \DateTime($event['event_date']);
                $eventTime = new \DateTime($event['event_time']);

                $slug = strtolower(
                    trim(
                        preg_replace(
                            '/[^A-Za-z0-9]+/',
                            '-',
                            $event['title']
                        ),
                        '-'
                    )
                );

                $status = $event['event_date'] >= date('Y-m-d')
                    ? 'upcoming'
                    : 'past';

                $galleryImages = [];

                if (!empty($event['gallery_images'])) {
                    $galleryImages = array_map(
                        function ($image) {
                            return '/uploads/events/' . $image;
                        },
                        explode('|||', $event['gallery_images'])
                    );
                }

                return [
                    'id' => (int) $event['id'],
                    'event_id' => $event['event_id'],
                    'title' => $event['title'],
                    'slug' => $slug,

                    'date' => $eventDate->format('d'),
                    'month' => strtoupper($eventDate->format('M')),
                    'day' => $eventDate->format('l'),

                    'category' => $event['category'],
                    'time' => $eventTime->format('g:i A'),
                    'location' => $event['event_location'],
                    'description' => $event['description'],
                    'short_description' => $event['short_description'],
                    'status' => $status,
                    'attendees' => 0,
                    'photos' => (int) $event['photos'],
                    'image' => '/uploads/events/' . $event['cover_image'],
                    'gallery' => $galleryImages,
                ];

            }, $events);

            echo json_encode([
                'status' => 'success',
                'events' => $formattedEvents
            ]);

        } catch (\Throwable $e) {

            error_log(
                'fetchEvent Error: ' . $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch events'
            ]);
        }
    }

    public function publicEvent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request method'
            ]);
            return;
        }

        try {
            $sql = "
                SELECT
                    e.*,
                    COUNT(eg.id) AS photos,
                    GROUP_CONCAT(
                        eg.image
                        ORDER BY eg.id ASC
                        SEPARATOR '|||'
                    ) AS gallery_images
                FROM events e
                LEFT JOIN event_gallery eg
                    ON e.event_id = eg.event_id
                GROUP BY e.id
                ORDER BY e.event_date ASC, e.event_time ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedEvents = [];

            foreach ($events as $event) {

                $eventDate = new \DateTime($event['event_date']);
                $eventTime = new \DateTime($event['event_time']);

                $slug = strtolower(
                    trim(
                        preg_replace(
                            '/[^A-Za-z0-9]+/',
                            '-',
                            $event['title']
                        ),
                        '-'
                    )
                );

                $status = $event['event_date'] >= date('Y-m-d')
                    ? 'upcoming'
                    : 'past';

                $images = [];

                if (!empty($event['gallery_images'])) {
                    $images = array_map(
                        function ($image) {
                            return '/uploads/events/' . $image;
                        },
                        explode('|||', $event['gallery_images'])
                    );
                }

                $formattedEvents[$slug] = [
                    'id' => (int) $event['id'],
                    'event_id' => $event['event_id'],
                    'title' => $event['title'],
                    'slug' => $slug,
                    'category' => $event['category'],
                    'status' => $status,
                    'date' => $eventDate->format('F j, Y'),
                    'time' => $eventTime->format('g:i A'),
                    'location' => $event['event_location'],
                    'description' => $event['description'],
                    'short_description' => $event['short_description'],
                    'attendees' => 0,
                    'photos' => (int) $event['photos'],
                    'image' => '/uploads/events/' . $event['cover_image'],
                    'month' => strtoupper($eventDate->format('M')),
                    'day' => $eventDate->format('l'),
                    'sessions' => [],
                    'highlights' => [],
                    'images' => $images
                ];
            }

            echo json_encode([
                'status' => 'success',
                'events' => $formattedEvents
            ]);

        } catch (\Throwable $e) {

            error_log(
                'fetchEvent Error: ' . $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch events'
            ]);
        }
    }
}