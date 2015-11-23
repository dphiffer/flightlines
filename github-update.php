<?php
/*

github-update.php
---
By Dan Phiffer <dan@phiffer.org>
Based on https://gist.github.com/webjay/3915531

1. Go to https://github.com/[user]/[repo]/settings/hooks/new
2. Payload URL: public URL of this script
3. Content Type: application/x-www-form-urlencoded
4. Disable SSL verification if necessary
5. Which events would you like to trigger this webhook? Just the push event.
6. Enable the active checkbox
7. Click the [Add webhook] button

*/

if (empty($_POST['payload'])) {
	die("Missing 'payload' POST parameter.");
}

function github_update_syscall($cmd, $cwd = null) {
	if (empty($cwd)) {
		$cwd = __DIR__;
	}
	$descriptorspec = array(
		1 => array('pipe', 'w'), // stdout is a pipe that the child will write to
		2 => array('pipe', 'w')  // stderr
	);
	$resource = proc_open($cmd, $descriptorspec, $pipes, $cwd);
	if (is_resource($resource)) {
		$output = stream_get_contents($pipes[2]);
		$output .= PHP_EOL;
		$output .= stream_get_contents($pipes[1]);
		$output .= PHP_EOL;
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($resource);
		return $output;
	}
}

function github_update_current_branch($cwd = null) {
	if (empty($cwd)) {
		$cwd = __DIR__;
	}
	$result = github_update_syscall('git branch', $cwd);
	if (preg_match('/\\* (.*)/', $result, $matches)) {
		return $matches[1];
	}
	return 'master';
}

ignore_user_abort(true);
$payload = json_decode($_POST['payload']);

// which branch was committed?
$branch = 'unknown';
if (!empty($payload->ref)) {
	$branch = substr($payload->ref, strrpos($payload->ref, '/') + 1);
}

// If your website directories have the same name as your repository this would work.
$repository = $payload->repository->full_name;
$curr_branch = github_update_current_branch();

// only pull if we are on the same branch
if ($branch != $curr_branch) {
	die("Only listening for updates to $curr_branch (not branch $branch).");
}

// pull from $branch
$cmd = sprintf('git pull origin %s', $branch);
$result = github_update_syscall($cmd);

$output = '';

// append commits
foreach ($payload->commits as $commit) {
	$output .= "{$commit->author->name} ({$commit->author->username})\n";
	foreach (array('added', 'modified', 'removed') as $action) {
		if (count($commit->{$action})) {
			$output .= sprintf('%s: %s; ', $action, implode(',', $commit->{$action}));
		}
	}
	$output .= PHP_EOL;
	$output .= sprintf('because: %s', $commit->message);
	$output .= PHP_EOL;
	$output .= $commit->url;
	$output .= PHP_EOL;
}

// append git result
$output .= PHP_EOL;
$output .= $result;

// All done here
die($output);

?>
