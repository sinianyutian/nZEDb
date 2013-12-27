<?php

/**
 * Copyright (C) 2013 nZEDb
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../www/config.php';
require_once nZEDb_LIB . 'framework/db.php';
require_once nZEDb_LIB . 'ColorCLI.php';

$output = new ColorCLI();

$warning = <<<WARNING
This script will (re)set the password hashes for older hashes (pre Db patch
158, others are ignored), with the user's email as the new password.
  It is intended for use ONLY if you have a *lot* of users, as this is not
secure (a user's email addresses may be known to other users). If you only have
a few users then run setUsersPasswordHash.php for each of them instead.
WARNING;

echo $output->warning($warning);
if ($argc != 2) {
	exit($output->error("\nWrong number of parameters\nUsage: php {$argv[0]} <IUnderStandTheRisks>"));
} else if ($argv[1] !== 1 && $argv[1] != '<IUnderStandTheRisks>' && $argv[1] != 'IUnderStandTheRisks' && $argv[1] != 'true') {
	exit($output->error("\nInvalid parameter(s)\nUsage: php {$argv[0]} <IUnderStandTheRisks>"));
}


$db = new DB();
$query = "SELECT `id`, `username`, `email`, `password` FROM users";

$users = $db->query($query);
$update = $db->Prepare('UPDATE users SET password = :password WHERE id = :id');

foreach($users as $user)
{
	if (needUpdate($user)) {
		$hash = crypt($user['email']);
		$update->execute([':password' => $hash, ':id' => $user['id']]);
		echo $output->primary('Updating hash for user:') . $user['username'];
	}
}

function needUpdate($user)
{
	global $output;
	$status = true;
	if (empty($user['email'])) {
		$status = false;
		echo $output->error('Cannot update password hash - Email is not set for user: ' . $user['username']);
	} else if (preg_match('#^\$.+$#', $user['password'])) {
		$status = false;
		echo $user['username'] . $output->primary(' is already using new style hash ;-)');
	}
	return $status;
}
?>