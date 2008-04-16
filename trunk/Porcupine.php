<?php
#
# Copyright 2008 Geoff Catlin
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

//define('PORCUPINE_DIR', dirname(__FILE__));
//set_include_path(get_include_path().DIRECTORY_SEPARATOR.PORCUPINE_DIR);

class Application {
	protected $debug = false;
	protected $url_mapping = array();

	public function __construct($url_mapping, $debug=false) {
		$this->debug = (bool) $debug;

		foreach ($url_mapping as $pattern => $resource_name) {
			if ($pattern[0] != '^') {
				$pattern = '^' . $pattern;
			}
			if ($pattern[strlen($pattern)-1] != '$') {
				$pattern .= '$';
			}
			$pattern = "!{$pattern}!";

			$this->url_mapping[$pattern] = $resource_name;
		}
	}

	public function run() {
		try {
			$request = new Request(); 
			$response = new Response(); 
			$resource = null;

			foreach ($this->url_mapping as $pattern => $resource_name) {
				if (preg_match($pattern, $request->path, $groups)) {
					if (!class_exists($resource_name, $autoload=false) && !@include("{$resource_name}.php")) {
					//	require "{$resource_name}.php";
					}
					$resource = new $resource_name($request, $response);
					break;
				}
			}
			
			if ($resource) {
				try {
					// do session/cookie stuff here?
					$method = strtolower($request->method);
					if ($method == 'get' || $method == 'post' || $method == 'put' || $method == 'delete' || $method == 'head' || $method == 'options' || $method == 'trace') {
						// do authentication stuff here?
						call_user_func_array(array($resource, $method), $groups);
					} else {
						$resource->error(Response::NotImplemented);
					}
				} catch (Exception $e) {
					$resource->handleException($e, $this->debug);
				}
			} else {
				$response->status(Response::NotFound)->clear();
			}

			return $response->send();
		} catch (Exception $e) {
			if ($this->debug) {
				try {
					$request->handleException($e);
				} catch (Exception $e) {
					// custom exception handler failed
				}
			} else {
				// custom exception handler failed
			}
		}
	}
	
	protected function init($routes) {
	}
}

class WebApplication extends Application {
}

// An HTTP request message
class Request {
	protected $params = array();

	public function __construct() {
		$method = strtoupper($_SERVER['REQUEST_METHOD']);
		if ($method == 'POST' && isset($_POST['PUT'])) {
			$method == 'PUT';
		}
		elseif ($method == 'POST' && isset($_POST['DELETE'])) {
			$method == 'DELETE';
		}
		elseif ($method == 'PUT' || $method == 'DELETE') {
			// this assumes that the PUT body is in the form: a=b&c=d...
			$input = file_get_contents('php://input');
			$params = explode('&', $input);
			foreach ($params as $param) {
				list($name, $value) = explode('=', $param);
				$_REQUEST[$name] = $_POST[$name] = $value;
			}
		}
		$this->params['method'] = $method;

		$path = '/';
		if (isset($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO']) > 1) {
			$path = rtrim($_SERVER['PATH_INFO'], '/');
		}
		$this->params['path'] = $path;

		//$charset = self::NoDefault;
		//if (strpos($_SERVER['CONTENT_TYPE']) === false) {
		//	$charset = 'utf-8';
		//}
	}

	protected function __get($name) {
		if (isset($this->params[$name])) {
			return $this->params[$name];
		}
		if (isset($_REQUEST[$name])) {
			return $_REQUEST[$name];
		}
		if (isset($_SERVER[$name])) {
			return $_SERVER[$name];
		}
		return null;
	}

	protected function __isset($name) {
		return (isset($this->params[$name]) || isset($_REQUEST[$name]) || isset($_SERVER[$name]));
	}
}

// A network data object or service that can be identified by a URI
class Resource {
	protected $request;
	protected $response;

	public function __construct(Request $request, Response $response) {
		$this->request = $request;
		$this->response = $response;
	}

	public function delete() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function error($status=Response::InternalServerError) {
		return $this->response->status($status)->clear();
	}

	public function get() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function handleException(Exception $e, $debug=false) {
		$this->error(Response::InternalServerError);
		ob_start();
		debug_print_backtrace();
		$backtrace = ob_get_clean();
		//$this->log->error($backtrace);
		if ($debug) {
			$this->response->headers['Content-Type'] = 'text/plain';
			$this->write($backtrace);
		}
	}

	public function head() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function options() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function post() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function put() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function redirect($uri, $permanent=false) {
		$this->response->status(($permanent ? Response::MovedPermanently : Response::Found));
		$this->response->headers['Location'] = Uri::join($this->request->uri, $uri);
		$this->response->clear();
		return $this;
	}

	public function trace() {
		return $this->error(Response::MethodNotAllowed);
	}

	public function write($str) {
		return $this->response->write($str);
	}
}

// An HTTP response message
class Response {
	const OK = 200;
	const Created = 201;
	const Accepted = 202;
	const NonAuthoritativeInformation = 203;
	const NoContent = 204;
	const ResetContent = 205;
	const PartialContent = 206;
	const MultipleChoices = 300;
	const MovedPermanently = 301;
	const Found = 302;
	const SeeOther = 303;
	const NotModified = 304;
	const UseProxy = 305;
	const TemporaryRedirect = 307;
	const BadRequest = 400;
	const Unauthorized = 401;
	const PaymentRequired = 402;
	const Forbidden = 403;
	const NotFound = 404;
	const MethodNotAllowed = 405;
	const NotAcceptable = 406;
	const ProxyAuthenticationRequired = 407;
	const RequestTimeout = 408;
	const Conflict = 409;
	const Gone = 410;
	const LengthRequired = 411;
	const PreconditionFailed = 412;
	const RequestEntityTooLarge = 413;
	const RequestURITooLarge = 414;
	const UnsupportedMediaType = 415;
	const RequestedRangeNotSatisfiable = 416;
	const ExpectationFailed = 417;
	const InternalServerError = 500;
	const NotImplemented = 501;
	const BadGateway = 502;
	const ServiceUnavailable = 503;
	const GatewayTimeOut = 504;
	const HTTPVersionNotSupported = 505;

	protected $body;
	protected $headers;
	protected $reasons = array(
		self::OK => 'OK',
		self::Created => 'Created',
		self::Accepted => 'Accepted',
		self::NonAuthoritativeInformation => 'Non Authoritative Information',
		self::NoContent => 'No Content',
		self::ResetContent => 'Reset Content',
		self::PartialContent => 'Partial Content',
		self::MultipleChoices => 'Multiple Choices',
		self::MovedPermanently => 'Moved Permanently',
		self::Found => 'Found',
		self::SeeOther => 'See Other',
		self::NotModified => 'Not Modified',
		self::UseProxy => 'Use Proxy',
		self::TemporaryRedirect => 'Temporary Redirect',
		self::BadRequest => 'Bad Request',
		self::Unauthorized => 'Unauthorized',
		self::PaymentRequired => 'Payment Required',
		self::Forbidden => 'Forbidden',
		self::NotFound => 'Not Found',
		self::MethodNotAllowed => 'Method Not Allowed',
		self::NotAcceptable => 'Not Acceptable',
		self::ProxyAuthenticationRequired => 'Prox yAuthentication Required',
		self::RequestTimeout => 'Request Timeout',
		self::Conflict => 'Conflict',
		self::Gone => 'Gone',
		self::LengthRequired => 'Length Required',
		self::PreconditionFailed => 'Precondition Failed',
		self::RequestEntityTooLarge => 'Request Entity Too Large',
		self::RequestURITooLarge => 'Request URI Too Large',
		self::UnsupportedMediaType => 'Unsupported Media Type',
		self::RequestedRangeNotSatisfiable => 'Requested Range Not Satisfiable',
		self::ExpectationFailed => 'Expectation Failed',
		self::InternalServerError => 'Internal Server Error',
		self::NotImplemented => 'Not Implemented',
		self::BadGateway => 'Bad Gateway',
		self::ServiceUnavailable => 'Service Unavailable',
		self::GatewayTimeOut => 'Gateway Time Out',
		self::HTTPVersionNotSupported => 'HTTP Version Not Supported',
	);
	protected $status;
	
	public function __construct() {
		$this->headers['Content-Type'] = "text/html; charset=utf-8";
		$this->headers['Cache-Control'] = "no-cache";
		$this->status = self::OK;
	}

	public function __get($name) {
		if ($name == 'header') {
		}
	}

	public function __set($name, $value) {
		if ($name == 'header') {
		}
	}

	public function clear() {
		$this->body = '';
		return $this;
	}

	public function send() {
		// prevent sending headers more than once?
		// throw exception if send is called more than once?
		header("HTTP/1.1 {$this->status} {$this->reasons[$this->status]}");

		foreach ($this->headers as $header => $value) {
			header("{$header}: {$value}");
		}

		if (is_file($this->body)) {
			passthru($this->body);
		} else {
			echo $this->body;
		}

		return $this;
	}

	public function status($code) {
		$this->status = $code;
		return $this;
	}

	public function write($str) {
		$this->body .= $str;
		return $this;
	}
}

class Template {
	protected $parsed = null;
	protected $template = null;
	protected $vars = array();

	public function __construct($template, $vars=null) {
		$this->template = $template;

		if (is_array($vars)) {
			foreach ($vars as $name => $value) {
				$this->__set($name, $value);
			}
		}
	}

	public function __get($name) {
		return (isset($this->vars[$name]) ? $this->vars[$name] : null);
	}

	public function __set($name, $value) {
		if ($name === 'this') {
			throw new Exception("'this' is not an allowed name for a template variable");
		}
		$this->vars[$name] = $value;
		$this->parsed = null;
	}

	public function parse() {
		if ($this->parsed !== null || $this->template === null) {
			return $this->parsed;
		}

		if ($this->vars) {
			$__vars__ = array();
			foreach ($this->vars as $name => $value) {
				$__vars__[$name] = ($value instanceof Template ? $value->parse() : $value);
			}

			unset($name, $value);
			extract($__vars__, EXTR_REFS);
			unset($__vars__);
		}

		ob_start();

		// change error reporting to hide notices (for unset variables)
		// @TODO make this configurable
		//$__error_reporting__ = error_reporting(error_reporting() ^ E_NOTICE);

		if (!@include $this->template) {
			if (strpos($this->template, '<?php ') === false) {
				echo $this->template;
			} else {
				eval('?>' . $this->template);
				// hide all errors??
				//$eval = eval($this->template);
				//if ($eval === false) {
				//	ob_clean();
				//}
			}
		}

		// reset error reporting
		//error_reporting($__error_reporting__);

		return $this->parsed = ob_get_clean();
	}

}

?>