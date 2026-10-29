<?php
require __DIR__ . '/bootstrap.php';
[$script, $mode, $id, $revision, $production, $key, $wait] = $argv;
while (microtime(true) < (float) $wait) { usleep(10000); }
try {
    if ($mode === 'save') {
        $data = LPM_Service::editor((int) $id)['content'];
        $data['title'] = $key;
        if (isset($data['program']) && $data['program']==='') { $data['program']='reguler'; }
        $result = LPM_Service::save((int) $id, $data, (int) $revision, (int) $production);
    } else {
        $result = LPM_Service::publish((int) $id, (int) $revision, (int) $production, 'Concurrent publication', $key, $mode === 'restore' ? (int) ($argv[7] ?? 0) : null);
    }
    echo json_encode(['ok' => true, 'result' => $result]);
} catch (LPM_Conflict $e) { echo json_encode(['ok' => false, 'conflict' => true]); }
catch (Throwable $e) { echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit(1); }
