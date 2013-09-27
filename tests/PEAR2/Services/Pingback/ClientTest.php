<?php
namespace PEAR2\Services\Pingback;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \HTTP_Request2_Adapter_Mock
     */
    protected $mock;

    /**
     * @var Client
     */
    protected $client;

    protected static $xmlPingback = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<methodResponse>
 <params>
  <param><value><string>Pingback received and processed</string></value></param>
 </params>
</methodResponse>
XML;

    protected static $jsonWebmention = <<<JSN
{"result":"Webmention received and processed"}
JSN;

    protected static $htmlWithPingback = <<<HTM
<html>
 <head>
  <title>article</title>
  <link rel="pingback" href="http://example.org/article-pingback-server" />
 </head>
 <body>
  <p>This is an article</p>
 </body>
</html>
HTM;

    protected static $htmlWithWebmention = <<<HTM
<html>
 <head>
  <title>article</title>
  <link rel="webmention" href="http://example.org/article-webmention-server" />
 </head>
 <body>
  <p>This is an article</p>
 </body>
</html>
HTM;

    protected static $htmlWithWebmentionOrg = <<<HTM
<html>
 <head>
  <title>article</title>
  <link rel="http://webmention.org/" href="http://example.org/article-webmention-server" />
 </head>
 <body>
  <p>This is an article</p>
 </body>
</html>
HTM;

    protected static $htmlWithInvalidPingback = <<<HTM
<html>
 <head>
  <title>article</title>
  <link rel="pingback" href="/path/to/pingback-server" />
 </head>
 <body>
  <p>This is an article</p>
 </body>
</html>
HTM;

    protected static $htmlWithoutPingback = <<<HTM
<html>
 <head>
  <title>article</title>
 </head>
 <body>
  <p>This is an article without a pingback server</p>
 </body>
</html>
HTM;



    public function setUp()
    {
        $this->mock = new \HTTP_Request2_Adapter_Mock();
        $req = new \HTTP_Request2();
        $req->setAdapter($this->mock);

        $this->client = new Client();
        $this->client->setRequest($req);
    }

    public function testSendPingback()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: http://example.org/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );
        //pingback request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Content-Type: text/xml\r\n"
            . "\r\n"
            . static::$xmlPingback,
            'http://example.org/pingback-server'
        );

        $res = $this->client->send(
            'http://example.org/source',
            'http://example.org/article'
        );
        $this->assertFalse($res->isError());
        $this->assertNull($res->getCode());
        $this->assertEquals(
            'Pingback received and processed', $res->getMessage()
        );
    }

    public function testSendWebmention()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "Link: <http://example.org/webmention-server>; rel=\"webmention\"\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );
        //pingback request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Content-Type: application/json\r\n"
            . "\r\n"
            . static::$jsonWebmention,
            'http://example.org/webmention-server'
        );

        $res = $this->client->send(
            'http://example.org/source',
            'http://example.org/article'
        );
        $this->assertFalse($res->isError());
        $this->assertNull($res->getCode());
        $this->assertEquals(
            'Webmention received and processed', $res->getMessage()
        );
    }

    public function testSendInvalidSource()
    {
        $res = $this->client->send(
            'article.htm', 'http://example.org/'
        );
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'Source URI invalid: article.htm', $res->getMessage()
        );
    }

    public function testSendEmptySource()
    {
        $res = $this->client->send(
            '', 'http://example.org/'
        );
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'Source URI invalid: ', $res->getMessage()
        );
    }

    public function testSendInvalidTarget()
    {
        $res = $this->client->send(
            'http://example.org/', 'target.htm'
        );
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'Target URI invalid: target.htm', $res->getMessage()
        );
    }

    public function testDiscoverServerGet()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n",
            'http://example.org/article'
        );
        //GET request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: http://example.org/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/pingback-server', $res->uri);
        $this->assertEquals('pingback', $res->type);
    }

    public function testDiscoverServerGetInvalid()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n",
            'http://example.org/article'
        );
        //GET request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: /path/to/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('\PEAR2\Services\Pingback\Response\Ping', $res);
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'GET X-Pingback server URI invalid: /path/to/pingback-server',
            $res->getMessage()
        );
    }

    public function testDiscoverServerHeadPingback()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: http://example.org/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/pingback-server', $res->uri);
        $this->assertEquals('pingback', $res->type);
    }

    public function testDiscoverServerHeadPingbackInvalid()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: /path/to/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('\PEAR2\Services\Pingback\Response\Ping', $res);
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'HEAD X-Pingback server URI invalid: /path/to/pingback-server',
            $res->getMessage()
        );
    }

    public function testDiscoverServerHeadWebmention()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "Link: <http://example.org/webmention-server>; rel=\"webmention\"\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/webmention-server', $res->uri);
        $this->assertEquals('webmention', $res->type);
    }

    public function testDiscoverServerHeadWebmentionInvalid()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "Link: </webmention-server>; rel=\"webmention\"\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('\PEAR2\Services\Pingback\Response\Ping', $res);
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'HEAD Link webmention server URI invalid: /webmention-server',
            $res->getMessage()
        );
    }

    public function testDiscoverServerHeadMethodNotAllowed()
    {
        //HEAD request
        $this->mock->addResponse(
            "HTTP/1.0 405 Method not allowed\r\n",
            'http://example.org/article'
        );
        //GET request
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "Foo: bar\r\n"
            . "X-Pingback: http://example.org/pingback-server\r\n"
            . "Bar: foo\r\n",
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/pingback-server', $res->uri);
        $this->assertEquals('pingback', $res->type);
    }

    public function testDiscoverServerTargetUriNotFoundHead()
    {
        $this->mock->addResponse(
            "HTTP/1.0 404 Not Found\r\n",
            'http://example.org/article'
        );
        $res = $this->client->send(
            'http://example.org/myblog',
            'http://example.org/article'
        );
        $this->assertTrue($res->isError());
        $this->assertEquals(States::TARGET_URI_NOT_FOUND, $res->getCode());
        $this->assertEquals('Error fetching target URI via HEAD', $res->getMessage());
        $this->assertNull($res->getResponse());
    }

    public function testSendTargetUriNotFoundHeadDebug()
    {
        $this->mock->addResponse(
            "HTTP/1.0 404 Not Found\r\n",
            'http://example.org/article'
        );
        $this->client->setDebug(true);
        $res = $this->client->send(
            'http://example.org/myblog',
            'http://example.org/article'
        );
        $this->assertTrue($res->isError());
        $this->assertNotNull($res->getResponse());
        $this->assertInstanceOf('\HTTP_Request2_Response', $res->getResponse());
        $this->assertEquals(404, $res->getResponse()->getStatus());
    }

    public function testDiscoverServerTargetUriNotFoundGet()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 404 Not Found\r\n",
            'http://example.org/article'
        );

        $res = $this->client->send(
            'http://example.org/myblog',
            'http://example.org/article'
        );
        $this->assertTrue($res->isError());
        $this->assertEquals(States::TARGET_URI_NOT_FOUND, $res->getCode());
        $this->assertEquals('Error fetching target URI', $res->getMessage());
    }


    public function testSendTargetUriNotFoundGetDebug()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 404 Not Found\r\n",
            'http://example.org/article'
        );

        $this->client->setDebug(true);
        $res = $this->client->send(
            'http://example.org/myblog',
            'http://example.org/article'
        );
        $this->assertTrue($res->isError());
        $this->assertNotNull($res->getResponse());
        $this->assertInstanceOf('\HTTP_Request2_Response', $res->getResponse());
        $this->assertEquals(404, $res->getResponse()->getStatus());
    }

    public function testDiscoverServerHtmlPingback()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n"
            . static::$htmlWithPingback,
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/article-pingback-server', $res->uri);
        $this->assertEquals('pingback', $res->type);
    }

    public function testDiscoverServerHtmlWebmention()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n"
            . static::$htmlWithWebmention,
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/article-webmention-server', $res->uri);
        $this->assertEquals('webmention', $res->type);
    }

    public function testDiscoverServerHtmlWebmentionOrg()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n"
            . static::$htmlWithWebmentionOrg,
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('PEAR2\Services\Pingback\Server\Info', $res);
        $this->assertEquals('http://example.org/article-webmention-server', $res->uri);
        $this->assertEquals('webmention', $res->type);
    }

    public function testDiscoverServerHtmlInvalid()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n"
            . static::$htmlWithInvalidPingback,
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('\PEAR2\Services\Pingback\Response\Ping', $res);
        $this->assertTrue($res->isError());
        $this->assertEquals(States::INVALID_URI, $res->getCode());
        $this->assertEquals(
            'HTML head link server URI invalid',
            $res->getMessage()
        );
    }

    public function testDiscoverServerHtmlNoServer()
    {
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n",
            'http://example.org/article'
        );
        $this->mock->addResponse(
            "HTTP/1.0 200 OK\r\n"
            . "\r\n"
            . static::$htmlWithoutPingback,
            'http://example.org/article'
        );

        $res = $this->client->discoverServer(
            'http://example.org/article'
        );
        $this->assertInstanceOf('\PEAR2\Services\Pingback\Response\Ping', $res);
        $this->assertTrue($res->isError());
        $this->assertEquals(States::PINGBACK_UNSUPPORTED, $res->getCode());
        $this->assertEquals(
            'No pingback server found for URI', $res->getMessage()
        );
    }

    public function testGetRequestEmpty()
    {
        $cl = new Client();
        $req = $cl->getRequest();
        $this->assertInstanceOf('\HTTP_Request2', $req);
    }

    public function testGetRequestSet()
    {
        $cl = new Client();
        $req = $cl->getRequest();
        $this->assertInstanceOf('\HTTP_Request2', $req);

        $req2 = $cl->getRequest();
        $this->assertSame($req, $req2);
    }
}
?>
