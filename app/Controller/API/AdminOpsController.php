<?php

namespace App\Controller\API;

use App\Core\DB;
use PDO;
use Exception;
use Dotenv\Dotenv;

class AdminOpsController{
    private $pdo;
    private $userId;

    public function __construct(){
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $this->pdo = DB::connection();

        $this->userId = $_SESSION['user']['user_id'] ?? null;
    }

    public function CreateEvent(){

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!$this->userId) {
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $shortDescription = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['content'] ?? '');
        $url = trim($_POST['registrationUrl'] ?? '');

        if ($title === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Title is required']
            );
            return;
        }

        if ($shortDescription === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Short description is required']
            );
            return;
        }

        if ($category === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Category is required']
            );
            return;
        }

        if ($date === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Date is required']
            );
            return;
        }

        if ($time === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Time is required']
            );
            return;
        }

        if ($location === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Location is required']
            );
            return;
        }

        if ($description === '') {
            echo json_encode(
                ['status' => 'error', 'message' => 'Content is required']
            );
            return;
        }

         /*
        * Cover image validation
        */
        if (
            !isset($_FILES['coverImage']) ||
            $_FILES['coverImage']['error'] === UPLOAD_ERR_NO_FILE
        ) {
            echo json_encode(
                ['status' => 'error', 'message' => 'Cover image is required']
            );
            return;
        }

        $eventId = uniqid("event_");

        /*
        * Upload directory
        */
        $uploadDirectory = __DIR__ . '/../../../public/uploads/events/';

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        /*
        * Keep track of uploaded files.
        * If something fails later, we can delete them.
        */
        $uploadedFiles = [];

        try {
            /*
            * Validate and upload cover image
            */
            $coverFile = $_FILES['coverImage'];

            $coverImage = $this->uploadEventImage(
                $coverFile,
                $uploadDirectory
            );

            $uploadedFiles[] = $coverImage;

            /*
            * Start database transaction
            */
            $this->pdo->beginTransaction();

            /*
            * Insert event
            */
            $sql = "
                INSERT INTO events (
                    event_id,
                    user_id, 
                    title, 
                    description, 
                    short_description, 
                    category, 
                    cover_image, 
                    event_date, 
                    event_time, 
                    event_location, 
                    event_url
                )
                VALUES (
                    :event_id,
                    :user_id,
                    :title,
                    :description,
                    :short_description,
                    :category,
                    :cover_image,
                    :event_date,
                    :event_time,
                    :location,
                    :registration_url
                )
            ";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':event_id' => $eventId,
                ':user_id' => $this->userId,
                ':title' => $title,
                ':short_description' => $shortDescription,
                ':category' => $category,
                ':event_date' => $date,
                ':event_time' => $time,
                ':location' => $location,
                ':description' => $description,
                ':cover_image' => $coverImage,
                ':registration_url' => $url
            ]);

            /*
            * Gallery images
            */
            if (
                isset($_FILES['galleryImages']) &&
                isset($_FILES['galleryImages']['name']) &&
                is_array($_FILES['galleryImages']['name'])
            ) {

                $gallery = $_FILES['galleryImages'];

                foreach ($gallery['name'] as $index => $name) {

                    if (
                        $gallery['error'][$index] === UPLOAD_ERR_NO_FILE
                    ) {
                        continue;
                    }

                    $galleryFile = [
                        'name' => $gallery['name'][$index],
                        'type' => $gallery['type'][$index],
                        'tmp_name' => $gallery['tmp_name'][$index],
                        'error' => $gallery['error'][$index],
                        'size' => $gallery['size'][$index],
                    ];

                    $galleryImage = $this->uploadEventImage(
                        $galleryFile,
                        $uploadDirectory
                    );

                    $uploadedFiles[] = $galleryImage;

                    /*
                    * Insert gallery image
                    */
                    $gallerySql = "
                        INSERT INTO event_gallery (
                            event_id,
                            image
                        )
                        VALUES (
                            :event_id,
                            :image
                        )
                    ";

                    $galleryStmt = $this->pdo->prepare($gallerySql);

                    $galleryStmt->execute([
                        ':event_id' => $eventId,
                        ':image' => $galleryImage
                    ]);
                }
            }

            /*
            * Commit transaction
            */
            $this->pdo->commit();

            echo json_encode([
                'status' => 'success',
                'message' => 'Event created successfully',
                'event_id' => $eventId
            ]);
        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            foreach ($uploadedFiles as $file) {
                $filePath = $uploadDirectory . basename($file);

                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            error_log(
                'CreateEvent Error: ' . $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function uploadEventImage(
        array $file,
        string $uploadDirectory
    ): string {

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Image upload failed.');
        }

        /*
        * Maximum 5MB
        */
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new \Exception(
                'Image must not exceed 5MB.'
            );
        }

        /*
        * Check actual MIME type
        */
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file(
            $file['tmp_name']
        );

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new \Exception(
                'Only JPG, PNG and WebP images are allowed.'
            );
        }

        $extension = $allowedTypes[$mimeType];

        /*
        * Generate random filename
        */
        $filename =
            bin2hex(random_bytes(16)) .
            '.' .
            $extension;

        $destination =
            $uploadDirectory .
            $filename;

        if (!move_uploaded_file(
            $file['tmp_name'],
            $destination
        )) {
            throw new \Exception(
                'Could not save uploaded image.'
            );
        }

        /*
        * Return filename rather than filesystem path
        */
        return $filename;
    }

    public function fetchEvent()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request method'
            ]);
            return;
        }

        if (!$this->userId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not authenticated'
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

                    'category' => $event['category'],

                    'date' => $eventDate->format('F j, Y'),

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

    public function UploadGallery()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request method'
            ]);
            return;
        }

        if (!$this->userId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not authenticated'
            ]);
            return;
        }

        $eventColumnId = isset($_POST['event_id'])
            ? (int) $_POST['event_id']
            : 0;

        $getRealId = $this->pdo->prepare("
            SELECT * FROM events WHERE id = ?
        ");
        $getRealId->execute([$eventColumnId]);

        if ($getRealId->rowCount() === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Event not found'
            ]);
            return;
        }

        $eventId = $getRealId->fetch(PDO::FETCH_ASSOC)['event_id'];

        if ($eventId === null) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid event ID'
            ]);
            return;
        }

        if (
            !isset($_FILES['images']) ||
            empty($_FILES['images']['name'])
        ) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No images uploaded'
            ]);
            return;
        }

        $uploadDirectory = __DIR__ . '/../../../public/uploads/events/';

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        $uploadedImages = [];

        $files = $_FILES['images'];

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpFile = $files['tmp_name'][$i];

            $mimeType = mime_content_type($tmpFile);

            if (!isset($allowedTypes[$mimeType])) {
                continue;
            }

            $extension = $allowedTypes[$mimeType];

            $fileName =
                'gallery_' .
                bin2hex(random_bytes(16)) .
                '.' .
                $extension;

            $destination =
                $uploadDirectory . $fileName;

            if (!move_uploaded_file($tmpFile, $destination)) {
                continue;
            }

            $imagePath =
                '/uploads/events/' . $fileName;

            /*
            * Save image path in database
            */

            $stmt = $this->pdo->prepare("
                INSERT INTO event_gallery
                (event_id, image)
                VALUES (?, ?)
            ");

            $stmt->execute([
                $eventId,
                $imagePath
            ]);

            $uploadedImages[] = $imagePath;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages
        ]);
    }
}