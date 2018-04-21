<?php
use airmoi\FileMaker\Record;
#use airmoi\FileMaker\Exception;
use airmoi\FileMaker\FileMakerException;

use rts_scheduler\V1\Entities\User;

use Slim\Http\Request;
use Slim\Http\Response;

$app -> post('/auth', function(Request $request, Response $response, array $args){

	$tz_header = '';
	if($request->hasHeader('X-RTS-TIMEZONE')) {
		$tz_header = $request->getHeader('X-RTS-TIMEZONE');
	}

	$rtsNumber = $request -> getParsedBodyParam('rtsNumber', '');
	$validationNumber = $request -> getParsedBodyParam('validationNumber', '');
	$timeZone = $request->getParsedBodyParam('timeZone', $tz_header);

	// if the time zone has not been provided in the parameters, nor in the header,
	//  then set the default from the environment.
	if(empty($timeZone)){
		$timeZone = $_ENV['TIME_ZONE_DEFAULT'];
	}

	if(empty($rtsNumber)){
		return $response -> withStatus(400) -> withJson(error(-1,'No RTS Number specified.'));
	}
	if(empty($validationNumber)){
		return $response -> withStatus(400) -> withJson(error(-1,'No Validation Number specified.'));
	}

	$db = connectToDB('RTS',['errorHandling' => 'exception']);

	try {
		$q = $db -> newFindCommand('web_authenticate');
		$q -> addFindCriterion('cd_RTS', '=='.$rtsNumber);
		$q -> addFindCriterion('cd_PhantomAcct', '=='.$validationNumber);
		
		$r = $q -> execute();

		$userRecord = $r -> getFirstRecord();

		$user = User::generate(
			User::readFM($userRecord)
		);

		#Now generate the JWT for them
		$now = new DateTime();
		$future = new DateTime("now +2 hours");
		$base62 = new \Tuupola\Base62;
		$jti = $base62->encode(random_bytes(128));

		$secret = $_ENV['JWT_SECRET'];

		$payload = [
			'jti' => $jti,
			'iat' => $now -> getTimestamp(),
			'exp' => $future -> getTimeStamp(),
			'user_id' => $user['id'],
			'timezone' => $timeZone
		];

		$token = \Firebase\JWT\JWT::encode($payload, $secret, "HS256");

		return $response -> withJson(success(array_merge(
			[
				'user' => $user,
				'token' => $token
			]
		)));
	}
	catch(Exception $e) {
		$code = $e->getCode();
		$msg = $e->getMessage();
		if ($code === 401) {
			$response = $response -> withStatus(401);
		}
		if ($code === 0){
			$code = -1;
			$response = $response -> withStatus(503);
		}
		return $response -> withJson(error($code,$msg));
	}
});