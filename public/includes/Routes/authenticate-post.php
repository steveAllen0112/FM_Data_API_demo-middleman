<?php
use airmoi\FileMaker\Record;
#use airmoi\FileMaker\Exception;
use airmoi\FileMaker\FileMakerException;

use rts_scheduler\V1\Entities\User;

use Slim\Http\Request;
use Slim\Http\Response;

$app -> post('/auth', function(Request $request, Response $response, array $args){

	$rtsNumber = $request -> getParsedBodyParam('rtsNumber', '');
	$validationNumber = $request -> getParsedBodyParam('validationNumber', '');

	if(empty($rtsNumber)){
		return $response -> withJson(error(-1,'No RTS Number specified.'));
	}
	if(empty($validationNumber)){
		return $response -> withJson(error(-1,'No Validation Number specified.'));
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

		$secret = $_ENV['RTS_JWT_SECRET'];

		$payload = [
			'jti' => $jti,
			'iat' => $now -> getTimestamp(),
			'nbf' => $future -> getTimeStamp()
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
		return $response -> withJson(error($code,$msg));
	}
});