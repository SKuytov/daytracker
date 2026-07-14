<?php
require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$path   = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$db     = getDB();

switch ($path) {

    case 'tasks.list':
        $date   = $_GET['date'] ?? date('Y-m-d');
        $stmt   = $db->prepare("SELECT t.*, 
            (SELECT COUNT(*) FROM interruptions i WHERE i.task_id = t.id) AS interruption_count
            FROM tasks t 
            WHERE DATE(t.created_at) = ?
            ORDER BY t.created_at DESC");
        $stmt->execute([$date]);
        jsonResponse(['tasks' => $stmt->fetchAll()]);

    case 'tasks.create':
        $stmt = $db->prepare("INSERT INTO tasks (title, description, category, status) VALUES (?, ?, ?, 'idle')");
        $stmt->execute([
            $body['title']       ?? 'Untitled Task',
            $body['description'] ?? '',
            $body['category']    ?? 'general',
        ]);
        $id   = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['task' => $stmt->fetch()]);

    case 'tasks.start':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $now  = date('Y-m-d H:i:s');
        $db->prepare("INSERT INTO task_sessions (task_id, session_start) VALUES (?, ?)")->execute([$id, $now]);
        $db->prepare("UPDATE tasks SET status='running', start_time = COALESCE(start_time, ?), updated_at=NOW() WHERE id=?")->execute([$now, $id]);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['task' => $stmt->fetch()]);

    case 'tasks.pause':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE task_sessions SET session_end=?, seconds=TIMESTAMPDIFF(SECOND, session_start, ?) WHERE task_id=? AND session_end IS NULL ORDER BY id DESC LIMIT 1")->execute([$now, $now, $id]);
        $s = $db->prepare("SELECT SUM(seconds) as total FROM task_sessions WHERE task_id=?");
        $s->execute([$id]);
        $total = (int)($s->fetch()['total'] ?? 0);
        $db->prepare("UPDATE tasks SET status='paused', total_seconds=?, updated_at=NOW() WHERE id=?")->execute([$total, $id]);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['task' => $stmt->fetch()]);

    case 'tasks.stop':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE task_sessions SET session_end=?, seconds=TIMESTAMPDIFF(SECOND, session_start, ?) WHERE task_id=? AND session_end IS NULL")->execute([$now, $now, $id]);
        $s = $db->prepare("SELECT SUM(seconds) as total FROM task_sessions WHERE task_id=?");
        $s->execute([$id]);
        $total = (int)($s->fetch()['total'] ?? 0);
        $db->prepare("UPDATE tasks SET status='completed', end_time=?, total_seconds=?, updated_at=NOW() WHERE id=?")->execute([$now, $total, $id]);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['task' => $stmt->fetch()]);

    case 'tasks.update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $db->prepare("UPDATE tasks SET title=?, description=?, category=?, updated_at=NOW() WHERE id=?")->execute([
            $body['title']       ?? '',
            $body['description'] ?? '',
            $body['category']    ?? 'general',
            $id
        ]);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['task' => $stmt->fetch()]);

    case 'tasks.delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $db->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
        jsonResponse(['success' => true]);

    case 'tasks.get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('Missing task id');
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) jsonError('Task not found', 404);
        $s = $db->prepare("SELECT * FROM task_sessions WHERE task_id=? ORDER BY id ASC");
        $s->execute([$id]);
        $task['sessions'] = $s->fetchAll();
        $i = $db->prepare("SELECT * FROM interruptions WHERE task_id=? ORDER BY id ASC");
        $i->execute([$id]);
        $task['interruptions'] = $i->fetchAll();
        jsonResponse(['task' => $task]);

    case 'interruptions.start':
        $taskId = (int)($body['task_id'] ?? 0);
        if (!$taskId) jsonError('Missing task_id');
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE task_sessions SET session_end=?, seconds=TIMESTAMPDIFF(SECOND, session_start, ?) WHERE task_id=? AND session_end IS NULL")->execute([$now, $now, $taskId]);
        $db->prepare("INSERT INTO interruptions (task_id, type, note, started_at) VALUES (?, ?, ?, ?)")->execute([
            $taskId, $body['type'] ?? 'other', $body['note'] ?? '', $now
        ]);
        $intId = $db->lastInsertId();
        $db->prepare("UPDATE tasks SET status='paused', updated_at=NOW() WHERE id=?")->execute([$taskId]);
        $stmt  = $db->prepare("SELECT * FROM interruptions WHERE id=?");
        $stmt->execute([$intId]);
        jsonResponse(['interruption' => $stmt->fetch()]);

    case 'interruptions.stop':
        $intId = (int)($body['id'] ?? 0);
        if (!$intId) jsonError('Missing id');
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE interruptions SET ended_at=?, duration_seconds=TIMESTAMPDIFF(SECOND, started_at, ?) WHERE id=?")->execute([$now, $now, $intId]);
        $s = $db->prepare("SELECT task_id FROM interruptions WHERE id=?");
        $s->execute([$intId]);
        $taskId = (int)($s->fetch()['task_id'] ?? 0);
        if ($taskId) {
            $db->prepare("INSERT INTO task_sessions (task_id, session_start) VALUES (?, ?)")->execute([$taskId, $now]);
            $db->prepare("UPDATE tasks SET status='running', updated_at=NOW() WHERE id=?")->execute([$taskId]);
        }
        $stmt = $db->prepare("SELECT * FROM interruptions WHERE id=?");
        $stmt->execute([$intId]);
        jsonResponse(['interruption' => $stmt->fetch()]);

    case 'summary':
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(total_seconds) as total_tracked_seconds,
            SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) as running_tasks
            FROM tasks WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $stats = $stmt->fetch();
        $stmt2 = $db->prepare("SELECT COUNT(*) as total_interruptions, SUM(i.duration_seconds) as total_interruption_seconds
            FROM interruptions i JOIN tasks t ON t.id = i.task_id WHERE DATE(t.created_at) = ?");
        $stmt2->execute([$date]);
        $intStats = $stmt2->fetch();
        $stmt3 = $db->prepare("SELECT category, COUNT(*) as count, SUM(total_seconds) as seconds 
            FROM tasks WHERE DATE(created_at) = ? GROUP BY category");
        $stmt3->execute([$date]);
        $categories = $stmt3->fetchAll();
        jsonResponse(['date' => $date, 'tasks' => $stats, 'interruptions' => $intStats, 'categories' => $categories]);

    case 'days':
        $stmt = $db->prepare("SELECT DATE(created_at) as day, COUNT(*) as task_count FROM tasks GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 30");
        $stmt->execute();
        jsonResponse(['days' => $stmt->fetchAll()]);

    default:
        jsonError('Unknown action: ' . $path, 404);
}
