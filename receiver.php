<?php
// print_r($_REQUEST);
// echo"<br />";
// echo"<br />";
// echo"<br />";
// print_r(getallheaders());
// echo"<br />";
// echo"<br />";
// echo"<br />";
// echo(file_get_contents("php://input"));
// echo"<br />";
// echo"<br />";
// echo"<br />";
// print_r(json_decode(file_get_contents("php://input"), true));
// echo"<br />";
// echo"<br />";
// echo"<br />";
// print_r($_SERVER);
// echo"<br />";
// echo"<br />";
// echo"<br />";
// print_r($_SESSION);

// header('Location: https://www.cat-mh.com/interview/secure/index.html');
// http_response_code(302);
// setcookie("JSESSIONID", "ymOPw72ci6jeBibjJq3ca3np.ip-172-31-24-54; path=/interview; secure; HttpOnly", time()+3600, null, null, true, true);
// setcookie("AWSELB", "B3BD39AF16F0CF0B7377E928EC0A11EA291D20E9280654915BB0D2B0C64597CFFC8D11A03DDAA378D175FAFCC8D6F00508243C6EBB8818EFE4B8D60FA4409C8093B13010D6;PATH=/", time()+3600, null, null, true, true);

// test createInterview
$json = json_decode(file_get_contents("php://input"), true);
$mockAuthValues = [
	'interviewID' => 123,
	'identifier' => 'asdjfw98ej',
	'signature' => '98j4gjiog'
];
if ($json['organizationID'] == 114) exit(json_encode($mockAuthValues));

// test authInterview