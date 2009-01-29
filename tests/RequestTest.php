<?php


require_once dirname(__FILE__) . '/common.php';

/**
 * Tests of Auth_OAuth_Request
 *
 * The tests works by using OAuthTestUtils::build_request
 * to populare $_SERVER, $_GET & $_POST.
 *
 * Most of the base string and signature tests
 * are either very simple or based upon
 * http://wiki.oauth.net/TestCases
 *
 * @see http://wiki.oauth.net/TestCases
 */
class RequestTest extends PHPUnit_Framework_TestCase {	

	public function testFromRequestPost() {
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('foo'=>'bar', 'baz'=>'blargh'));
		$request = new Auth_OAuth_RequestImpl();
		
		$this->assertEquals('POST', $request->getMethod());
		$this->assertEquals('http://testbed/test', $request->getRequestUrl());
		$this->assertEquals(array('foo'=>'bar','baz'=>'blargh'), $request->getParameters());
	}
	
	public function testFromRequestPostGet() {
		OAuthTestUtils::build_request('GET', 'http://testbed/test', array('foo'=>'bar', 'baz'=>'blargh'));		
		$request = new Auth_OAuth_RequestImpl();
		
		$this->assertEquals('GET', $request->getMethod());
		$this->assertEquals('http://testbed/test', $request->getRequestUrl());
		$this->assertEquals(array('foo'=>'bar','baz'=>'blargh'), $request->getParameters());
	}
	
	public function testFromRequestHeader() {
		$test_header = 'OAuth realm="",oauth_foo=bar,oauth_baz="blargh"';
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('oauth_foo'=>'foo', 'oauth_baz'=>'baz'), $test_header);
		
		$request = new Auth_OAuth_RequestImpl();
		
		$this->assertEquals('POST', $request->getMethod());
		$this->assertEquals('http://testbed/test', $request->getRequestUrl());
		$this->assertEquals(array('oauth_foo'=>'bar','oauth_baz'=>'blargh'), $request->getParameters(), 'Failed to split auth-header correctly');
	}

	public function testNormalizeParameters() {
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('name'=>''));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'name=', $request->getNormalizedParameterString());

		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('a'=>'b'));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'a=b', $request->getNormalizedParameterString());
		
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('a'=>'b', 'c'=>'d'));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'a=b&c=d', $request->getNormalizedParameterString());
		
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('a'=>array('x!y', 'x y')));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'a=x%20y&a=x%21y', $request->getNormalizedParameterString());
		
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('x!y'=>'a', 'x'=>'a'));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'x=a&x%21y=a', $request->getNormalizedParameterString());
		
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('a'=>1, 'c'=>'hi there', 'f'=>array(25, 50, 'a'), 'z'=>array('p', 't')));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals( 'a=1&c=hi%20there&f=25&f=50&f=a&z=p&z=t', $request->getNormalizedParameterString());
	}
	
	public function testNormalizeHttpUrl() {
		OAuthTestUtils::build_request('POST', 'http://example.com', array());
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('http://example.com', $request->getRequestUrl());
		
		OAuthTestUtils::build_request('POST', 'https://example.com', array());
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('https://example.com', $request->getRequestUrl());
		
		// Tests that http on !80 and https on !443 keeps the port
		OAuthTestUtils::build_request('POST', 'https://example.com:80', array());
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('https://example.com:80', $request->getRequestUrl());
		
		OAuthTestUtils::build_request('POST', 'http://example.com:443', array());
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('http://example.com:443', $request->getRequestUrl());
	}

	public function testGetBaseString() {
		OAuthTestUtils::build_request('POST', 'http://testbed/test', array('n'=>'v'));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('POST&http%3A%2F%2Ftestbed%2Ftest&n%3Dv', Auth_OAuth_SignerImpl::getSignatureBaseString($request));
		
		OAuthTestUtils::build_request('GET', 'http://example.com', array('n'=>'v'));
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('GET&http%3A%2F%2Fexample.com&n%3Dv', Auth_OAuth_SignerImpl::getSignatureBaseString($request));
		
		
		$params = array('oauth_version'=>'1.0', 'oauth_consumer_key'=>'dpf43f3p2l4k3l03', 
					'oauth_timestamp'=>'1191242090', 'oauth_nonce'=>'hsu94j3884jdopsl',
					'oauth_signature_method'=>'PLAINTEXT', 'oauth_signature'=>'ignored');
		OAuthTestUtils::build_request('POST', 'https://photos.example.net/request_token', $params);			
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('POST&https%3A%2F%2Fphotos.example.net%2Frequest_token&oauth_'
							.'consumer_key%3Ddpf43f3p2l4k3l03%26oauth_nonce%3Dhsu94j3884j'
							.'dopsl%26oauth_signature_method%3DPLAINTEXT%26oauth_timestam'
							.'p%3D1191242090%26oauth_version%3D1.0', 
							Auth_OAuth_SignerImpl::getSignatureBaseString($request));
							
		$params = array('file'=>'vacation.jpg', 'size'=>'original', 'oauth_version'=>'1.0', 
					'oauth_consumer_key'=>'dpf43f3p2l4k3l03', 'oauth_token'=>'nnch734d00sl2jdk',
					'oauth_timestamp'=>'1191242096', 'oauth_nonce'=>'kllo9940pd9333jh',
					'oauth_signature'=>'ignored', 'oauth_signature_method'=>'HMAC-SHA1');
		OAuthTestUtils::build_request('GET', 'http://photos.example.net/photos', $params);			
		$request = new Auth_OAuth_RequestImpl();
		$this->assertEquals('GET&http%3A%2F%2Fphotos.example.net%2Fphotos&file%3Dvacation'
							.'.jpg%26oauth_consumer_key%3Ddpf43f3p2l4k3l03%26oauth_nonce%'
							.'3Dkllo9940pd9333jh%26oauth_signature_method%3DHMAC-SHA1%26o'
							.'auth_timestamp%3D1191242096%26oauth_token%3Dnnch734d00sl2jd'
							.'k%26oauth_version%3D1.0%26size%3Doriginal', 
							Auth_OAuth_SignerImpl::getSignatureBaseString($request));
							
	}

	/*
	// We only test two entries here. This is just to test that the correct 
	// signature method is chosen. Generation of the signatures is tested 
	// elsewhere, and so is the base-string the signature build upon.
	public function testBuildSignature() {
		$params = array('file'=>'vacation.jpg', 'size'=>'original', 'oauth_version'=>'1.0', 
					'oauth_consumer_key'=>'dpf43f3p2l4k3l03', 'oauth_token'=>'nnch734d00sl2jdk',
					'oauth_timestamp'=>'1191242096', 'oauth_nonce'=>'kllo9940pd9333jh',
					'oauth_signature'=>'ignored', 'oauth_signature_method'=>'HMAC-SHA1');
		OAuthTestUtils::build_request('GET', 'http://photos.example.net/photos', $params);			
		$request = new Auth_OAuth_RequestImpl();
		
		$cons = new OAuthConsumer('key', 'kd94hf93k423kf44');
		$token = new OAuthToken('token', 'pfkkdhi9sl3r4s00');
		$hmac = new OAuthSignatureMethod_HMAC_SHA1();
		$plaintext = new OAuthSignatureMethod_PLAINTEXT();
		
		$this->assertEquals('tR3+Ty81lMeYAr/Fid0kMTYa/WM=', $r->build_signature($hmac, $cons, $token));
		$this->assertEquals('kd94hf93k423kf44%26pfkkdhi9sl3r4s00', $r->build_signature($plaintext, $cons, $token));
	}

	public function testSign() {
		$params = array('file'=>'vacation.jpg', 'size'=>'original', 'oauth_version'=>'1.0', 
					'oauth_consumer_key'=>'dpf43f3p2l4k3l03', 'oauth_token'=>'nnch734d00sl2jdk',
					'oauth_timestamp'=>'1191242096', 'oauth_nonce'=>'kllo9940pd9333jh',
					'oauth_signature'=>'ignored', 'oauth_signature_method'=>'HMAC-SHA1');
		OAuthTestUtils::build_request('GET', 'http://photos.example.net/photos', $params);			
		$r = OAuthRequest::from_request();
		
		$cons = new OAuthConsumer('key', 'kd94hf93k423kf44');
		$token = new OAuthToken('token', 'pfkkdhi9sl3r4s00');
		$hmac = new OAuthSignatureMethod_HMAC_SHA1();
		$plaintext = new OAuthSignatureMethod_PLAINTEXT();
		
		$r->sign_request($hmac, $cons, $token);
		
		$params = $r->get_parameters();
		$this->assertEquals('HMAC-SHA1', $params['oauth_signature_method']);
		$this->assertEquals('tR3+Ty81lMeYAr/Fid0kMTYa/WM=', $params['oauth_signature']);
		
		$r->sign_request($plaintext, $cons, $token);
		
		$params = $r->get_parameters();
		$this->assertEquals('PLAINTEXT', $params['oauth_signature_method']);
		$this->assertEquals('kd94hf93k423kf44%26pfkkdhi9sl3r4s00', $params['oauth_signature']);
	}
	*/
}

?>
