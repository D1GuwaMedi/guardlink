<?php
require 'config.php'; need('management');
header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="visit_history.csv"');
$o = fopen('php://output', 'w'); fputcsv($o, ['Visitor', 'ID number', 'Visited resident', 'Check in', 'Check out']);
foreach ($pdo->query('SELECT i.visitor_name, i.visitor_id_no, u.name, v.check_in, v.check_out FROM visit_logs v JOIN invitations i ON i.id=v.invitation_id JOIN users u ON u.id=i.resident_id ORDER BY v.id DESC', PDO::FETCH_NUM) as $r) fputcsv($o, $r);
