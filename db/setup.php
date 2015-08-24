<?php

$db->query("
	CREATE TABLE video (
		id VARCHAR(255) PRIMARY KEY,
		location VARCHAR(255),
		status VARCHAR(255) DEFAULT 'pending',
		created DATETIME
	)
");