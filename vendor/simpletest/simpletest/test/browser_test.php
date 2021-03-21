<?php

require_once __DIR__ . '/../autorun.php';
require_once __DIR__ . '/../browser.php';
require_once __DIR__ . '/../user_agent.php';
require_once __DIR__ . '/../http.php';
require_once __DIR__ . '/../page.php';
require_once __DIR__ . '/../encoding.php';

Mock::generate('SimpleHttpResponse');
Mock::generate('SimplePage');
Mock::generate('SimpleForm');
Mock::generate('SimpleUserAgent');
Mock::generatePartial(
        'SimpleBrowser',
        'MockParseSimpleBrowser',
        array('createUserAgent', 'parse'));
Mock::generatePartial(
        'SimpleBrowser',
        'MockUserAgentSimpleBrowser',
        array('createUserAgent'));

class TestOfHistory extends UnitTestCase
{
    public function testEmptyHistoryHasFalseContents()
    {
        $history = new SimpleBrowserHistory();
        $this->assertIdentical($history->getUrl(), false);
        $this->assertIdentical($history->getParameters(), false);
    }

    public function testCannotMoveInEmptyHistory()
    {
        $history = new SimpleBrowserHistory();
        $this->assertFalse($history->back());
        $this->assertFalse($history->forward());
    }

    public function testCurrentTargetAccessors()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.here.com/'),
                new SimpleGetEncoding());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.here.com/'));
        $this->assertIdentical($history->getParameters(), new SimpleGetEncoding());
    }

    public function testSecondEntryAccessors()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.second.com/'),
                new SimplePostEncoding(array('a' => 1)));
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.second.com/'));
        $this->assertIdentical(
                $history->getParameters(),
                new SimplePostEncoding(array('a' => 1)));
    }

    public function testGoingBackwards()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.second.com/'),
                new SimplePostEncoding(array('a' => 1)));
        $this->assertTrue($history->back());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.first.com/'));
        $this->assertIdentical($history->getParameters(), new SimpleGetEncoding());
    }

    public function testGoingBackwardsOffBeginning()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $this->assertFalse($history->back());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.first.com/'));
        $this->assertIdentical($history->getParameters(), new SimpleGetEncoding());
    }

    public function testGoingForwardsOffEnd()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $this->assertFalse($history->forward());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.first.com/'));
        $this->assertIdentical($history->getParameters(), new SimpleGetEncoding());
    }

    public function testGoingBackwardsAndForwards()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.second.com/'),
                new SimplePostEncoding(array('a' => 1)));
        $this->assertTrue($history->back());
        $this->assertTrue($history->forward());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.second.com/'));
        $this->assertIdentical(
                $history->getParameters(),
                new SimplePostEncoding(array('a' => 1)));
    }

    public function testNewEntryReplacesNextOne()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.second.com/'),
                new SimplePostEncoding(array('a' => 1)));
        $history->back();
        $history->recordEntry(
                new SimpleUrl('http://www.third.com/'),
                new SimpleGetEncoding());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.third.com/'));
        $this->assertIdentical($history->getParameters(), new SimpleGetEncoding());
    }

    public function testNewEntryDropsFutureEntries()
    {
        $history = new SimpleBrowserHistory();
        $history->recordEntry(
                new SimpleUrl('http://www.first.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.second.com/'),
                new SimpleGetEncoding());
        $history->recordEntry(
                new SimpleUrl('http://www.third.com/'),
                new SimpleGetEncoding());
        $history->back();
        $history->back();
        $history->recordEntry(
                new SimpleUrl('http://www.fourth.com/'),
                new SimpleGetEncoding());
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.fourth.com/'));
        $this->assertFalse($history->forward());
        $history->back();
        $this->assertIdentical($history->getUrl(), new SimpleUrl('http://www.first.com/'));
        $this->assertFalse($history->back());
    }
}

class TestOfParsedPageAccess extends UnitTestCase
{
    public function loadPage(&$page)
    {
        $response = new MockSimpleHttpResponse($this);
        $agent    = new MockSimpleUserAgent($this);
        $agent->returns('fetchResponse', $response);

        $browser = new MockParseSimpleBrowser($this);
        $browser->returns('createUserAgent', $agent);
        $browser->returns('parse', $page);
        $browser->__constructor();

        $browser->get('http://this.com/page.html');

        return $browser;
    }

    public function testAccessorsWhenNoPage()
    {
        $agent   = new MockSimpleUserAgent($this);
        $browser = new MockParseSimpleBrowser($this);
        $browser->returns('createUserAgent', $agent);
        $browser->__constructor();
        $this->assertEqual($browser->getContent(), '');
    }

    public function testParse()
    {
        $page = new MockSimplePage();
        $page->returnsByValue('getRequest', "GET here.html\r\n\r\n");
        $page->returnsByValue('getRaw', 'Raw HTML');
        $page->returnsByValue('getTitle', 'Here');
        $page->returnsByValue('getFrameFocus', 'Frame');
        $page->returnsByValue('getMimeType', 'text/html');
        $page->returnsByValue('getResponseCode', 200);
        $page->returnsByValue('getAuthentication', 'Basic');
        $page->returnsByValue('getRealm', 'Somewhere');
        $page->returnsByValue('getTransportError', 'Ouch!');

        $browser = $this->loadPage($page);
        $this->assertEqual($browser->getRequest(), "GET here.html\r\n\r\n");
        $this->assertEqual($browser->getContent(), 'Raw HTML');
        $this->assertEqual($browser->getTitle(), 'Here');
        $this->assertEqual($browser->getFrameFocus(), 'Frame');
        $this->assertIdentical($browser->getResponseCode(), 200);
        $this->assertEqual($browser->getMimeType(), 'text/html');
        $this->assertEqual($browser->getAuthentication(), 'Basic');
        $this->assertEqual($browser->getRealm(), 'Somewhere');
        $this->assertEqual($browser->getTransportError(), 'Ouch!');
    }

    public function testLinkAffirmationWhenPresent()
    {
        $page = new MockSimplePage();
        $page->returnsByValue('getUrlsByLabel', array('http://www.nowhere.com'));
        $page->expectOnce('getUrlsByLabel', array('a link label'));
        $browser = $this->loadPage($page);
        $this->assertIdentical($browser->getLink('a link label'), 'http://www.nowhere.com');
    }

    public function testLinkAffirmationByIdWhenPresent()
    {
        $page = new MockSimplePage();
        $page->returnsByValue('getUrlById', 'a_page.com', array(99));
        $page->returnsByValue('getUrlById', false, array('*'));
        $browser = $this->loadPage($page);
        $this->assertIdentical($browser->getLinkById(99), 'a_page.com');
        $this->assertFalse($browser->getLinkById(98));
    }

    public function testSettingFieldIsPassedToPage()
    {
        $page = new MockSimplePage();
        $page->expectOnce('setField', array(new SelectByLabelOrName('key'), 'Value', false));
        $page->returnsByValue('getField', 'Value');
        $browser = $this->loadPage($page);
        $this->assertEqual($browser->getField('key'), 'Value');
        $browser->setField('key', 'Value');
    }
}

class TestOfBrowserNavigation extends UnitTestCase
{
    public function createBrowser($agent, $page)
    {
        $browser = new MockParseSimpleBrowser();
        $browser->returns('createUserAgent', $agent);
        $browser->returns('parse', $page);
        $browser->__constructor();

        return $browser;
    }

    public function testBrowserRequestMethods()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(
                0,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/get.req'), new SimpleGetEncoding()));
        $agent->expectAt(
                1,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/post.req'), new SimplePostEncoding()));
        $agent->expectAt(
                2,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/put.req'), new SimplePutEncoding()));
        $agent->expectAt(
                3,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/delete.req'), new SimpleDeleteEncoding()));
        $agent->expectAt(
                4,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/head.req'), new SimpleHeadEncoding()));
        $agent->expectCallCount('fetchResponse', 5);

        $page = new MockSimplePage();

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/get.req');
        $browser->post('http://this.com/post.req');
        $browser->put('http://this.com/put.req');
        $browser->delete('http://this.com/delete.req');
        $browser->head('http://this.com/head.req');
    }

    public function testClickLinkRequestsPage()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(
                0,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/page.html'), new SimpleGetEncoding()));
        $agent->expectAt(
                1,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/new.html'), new SimpleGetEncoding()));
        $agent->expectCallCount('fetchResponse', 2);

        $page = new MockSimplePage();
        $page->returnsByValue('getUrlsByLabel', array(new SimpleUrl('http://this.com/new.html')));
        $page->expectOnce('getUrlsByLabel', array('New'));
        $page->returnsByValue('getRaw', 'A page');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickLink('New'));
    }

    public function testClickLinkWithUnknownFrameStillRequestsWholePage()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(
                0,
                'fetchResponse',
                array(new SimpleUrl('http://this.com/page.html'), new SimpleGetEncoding()));
        $target = new SimpleUrl('http://this.com/new.html');
        $target->setTarget('missing');
        $agent->expectAt(
                1,
                'fetchResponse',
                array($target, new SimpleGetEncoding()));
        $agent->expectCallCount('fetchResponse', 2);

        $parsed_url = new SimpleUrl('http://this.com/new.html');
        $parsed_url->setTarget('missing');

        $page = new MockSimplePage();
        $page->returnsByValue('getUrlsByLabel', array($parsed_url));
        $page->returnsByValue('hasFrames', false);
        $page->expectOnce('getUrlsByLabel', array('New'));
        $page->returnsByValue('getRaw', 'A page');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickLink('New'));
    }

    public function testClickingMissingLinkFails()
    {
        $agent = new MockSimpleUserAgent($this);
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $page = new MockSimplePage();
        $page->returnsByValue('getUrlsByLabel', array());
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $this->assertTrue($browser->get('http://this.com/page.html'));
        $this->assertFalse($browser->clickLink('New'));
    }

    public function testClickIndexedLink()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(
                1,
                'fetchResponse',
                array(new SimpleUrl('1.html'), new SimpleGetEncoding()));
        $agent->expectCallCount('fetchResponse', 2);

        $page = new MockSimplePage();
        $page->returnsByValue(
                'getUrlsByLabel',
                array(new SimpleUrl('0.html'), new SimpleUrl('1.html')));
        $page->returnsByValue('getRaw', 'A page');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickLink('New', 1));
    }

    public function testClinkLinkById()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(1, 'fetchResponse', array(
                new SimpleUrl('http://this.com/link.html'),
                new SimpleGetEncoding()));
        $agent->expectCallCount('fetchResponse', 2);

        $page = new MockSimplePage();
        $page->returnsByValue('getUrlById', new SimpleUrl('http://this.com/link.html'));
        $page->expectOnce('getUrlById', array(2));
        $page->returnsByValue('getRaw', 'A page');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickLinkById(2));
    }

    public function testClickingMissingLinkIdFails()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $page = new MockSimplePage();
        $page->returnsByValue('getUrlById', false);
        $page->returnsByValue('getUrlsByLabel', array());

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertFalse($browser->clickLink(0));
    }

    public function testSubmitFormByLabel()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(1, 'fetchResponse', array(
                new SimpleUrl('http://this.com/handler.html'),
                new SimplePostEncoding(array('a' => 'A'))));
        $agent->expectCallCount('fetchResponse', 2);

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitButton', new SimplePostEncoding(array('a' => 'A')));
        $form->expectOnce('submitButton', array(new SelectByLabel('Go'), false));

        $page = new MockSimplePage();
        $page->returns('getFormBySubmit', $form);
        $page->expectOnce('getFormBySubmit', array(new SelectByLabel('Go')));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickSubmit('Go'));
    }

    public function testDefaultSubmitFormByLabel()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(1,  'fetchResponse', array(
                new SimpleUrl('http://this.com/page.html'),
                new SimpleGetEncoding(array('a' => 'A'))));
        $agent->expectCallCount('fetchResponse', 2);

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/page.html'));
        $form->returnsByValue('getMethod', 'get');
        $form->returnsByValue('submitButton', new SimpleGetEncoding(array('a' => 'A')));

        $page = new MockSimplePage();
        $page->returns('getFormBySubmit', $form);
        $page->expectOnce('getFormBySubmit', array(new SelectByLabel('Submit')));
        $page->returnsByValue('getRaw', 'stuff');
        $page->returnsByValue('getUrl', new SimpleUrl('http://this.com/page.html'));

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickSubmit());
    }

    public function testSubmitFormByName()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitButton', new SimplePostEncoding(array('a' => 'A')));

        $page = new MockSimplePage();
        $page->returns('getFormBySubmit', $form);
        $page->expectOnce('getFormBySubmit', array(new SelectByName('me')));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickSubmitByName('me'));
    }

    public function testSubmitFormById()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitButton', new SimplePostEncoding(array('a' => 'A')));
        $form->expectOnce('submitButton', array(new SelectById(99), false));

        $page = new MockSimplePage();
        $page->returns('getFormBySubmit', $form);
        $page->expectOnce('getFormBySubmit', array(new SelectById(99)));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickSubmitById(99));
    }

    public function testSubmitFormByImageLabel()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitImage', new SimplePostEncoding(array('a' => 'A')));
        $form->expectOnce('submitImage', array(new SelectByLabel('Go!'), 10, 11, false));

        $page = new MockSimplePage();
        $page->returns('getFormByImage', $form);
        $page->expectOnce('getFormByImage', array(new SelectByLabel('Go!')));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickImage('Go!', 10, 11));
    }

    public function testSubmitFormByImageName()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitImage', new SimplePostEncoding(array('a' => 'A')));
        $form->expectOnce('submitImage', array(new SelectByName('a'), 10, 11, false));

        $page = new MockSimplePage();
        $page->returns('getFormByImage', $form);
        $page->expectOnce('getFormByImage', array(new SelectByName('a')));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickImageByName('a', 10, 11));
    }

    public function testSubmitFormByImageId()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submitImage', new SimplePostEncoding(array('a' => 'A')));
        $form->expectOnce('submitImage', array(new SelectById(99), 10, 11, false));

        $page = new MockSimplePage();
        $page->returns('getFormByImage', $form);
        $page->expectOnce('getFormByImage', array(new SelectById(99)));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->clickImageById(99, 10, 11));
    }

    public function testSubmitFormByFormId()
    {
        $agent = new MockSimpleUserAgent();
        $agent->returns('fetchResponse', new MockSimpleHttpResponse());
        $agent->expectAt(1, 'fetchResponse', array(
                new SimpleUrl('http://this.com/handler.html'),
                new SimplePostEncoding(array('a' => 'A'))));
        $agent->expectCallCount('fetchResponse', 2);

        $form = new MockSimpleForm();
        $form->returnsByValue('getAction', new SimpleUrl('http://this.com/handler.html'));
        $form->returnsByValue('getMethod', 'post');
        $form->returnsByValue('submit', new SimplePostEncoding(array('a' => 'A')));

        $page = new MockSimplePage();
        $page->returns('getFormById', $form);
        $page->expectOnce('getFormById', array(33));
        $page->returnsByValue('getRaw', 'stuff');

        $browser = $this->createBrowser($agent, $page);
        $browser->get('http://this.com/page.html');
        $this->assertTrue($browser->submitFormById(33));
    }
}

class TestOfBrowserFrames extends UnitTestCase
{
    public function createBrowser($agent)
    {
        $browser = new MockUserAgentSimpleBrowser();
        $browser->returns('createUserAgent', $agent);
        $browser->__constructor();

        return $browser;
    }

    public function createUserAgent($pages)
    {
        $agent = new MockSimpleUserAgent();
        foreach ($pages as $url => $raw) {
            $url      = new SimpleUrl($url);
            $response = new MockSimpleHttpResponse();
            $response->returnsByValue('getUrl', $url);
            $response->returnsByValue('getContent', $raw);
            $agent->returns('fetchResponse', $response, array($url, '*'));
        }

        return $agent;
    }

    public function testSimplePageHasNoFrames()
    {
        $browser = $this->createBrowser($this->createUserAgent(
                array('http://site.with.no.frames/' => 'A non-framed page')));
        $this->assertEqual(
                $browser->get('http://site.with.no.frames/'),
                'A non-framed page');
        $this->assertIdentical($browser->getFrames(), 'http://site.with.no.frames/');
    }

    public function testFramesetWithSingleFrame()
    {
        $frameset = '<frameset><frame name="a" src="frame.html"></frameset>';
        $browser  = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.one.frame/'           => $frameset,
                'http://site.with.one.frame/frame.html' => 'A frame')));
        $this->assertEqual($browser->get('http://site.with.one.frame/'), 'A frame');
        $this->assertIdentical(
                $browser->getFrames(),
                array('a' => 'http://site.with.one.frame/frame.html'));
    }

    public function testTitleTakenFromFramesetPage()
    {
        $frameset = '<title>Frameset title</title>' .
                '<frameset><frame name="a" src="frame.html"></frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.one.frame/'           => $frameset,
                'http://site.with.one.frame/frame.html' => '<title>Page title</title>')));
        $browser->get('http://site.with.one.frame/');
        $this->assertEqual($browser->getTitle(), 'Frameset title');
    }

    public function testFramesetWithSingleUnnamedFrame()
    {
        $frameset = '<frameset><frame src="frame.html"></frameset>';
        $browser  = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.one.frame/'           => $frameset,
                'http://site.with.one.frame/frame.html' => 'One frame')));
        $this->assertEqual(
                $browser->get('http://site.with.one.frame/'),
                'One frame');
        $this->assertIdentical(
                $browser->getFrames(),
                array(1 => 'http://site.with.one.frame/frame.html'));
    }

    public function testFramesetWithMultipleFrames()
    {
        $frameset = '<frameset>' .
                '<frame name="a" src="frame_a.html">' .
                '<frame name="b" src="frame_b.html">' .
                '<frame name="c" src="frame_c.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.frames/'             => $frameset,
                'http://site.with.frames/frame_a.html' => 'A frame',
                'http://site.with.frames/frame_b.html' => 'B frame',
                'http://site.with.frames/frame_c.html' => 'C frame')));
        $this->assertEqual(
                $browser->get('http://site.with.frames/'),
                'A frameB frameC frame');
        $this->assertIdentical($browser->getFrames(), array(
                'a' => 'http://site.with.frames/frame_a.html',
                'b' => 'http://site.with.frames/frame_b.html',
                'c' => 'http://site.with.frames/frame_c.html'));
    }

    public function testFrameFocusByName()
    {
        $frameset = '<frameset>' .
                '<frame name="a" src="frame_a.html">' .
                '<frame name="b" src="frame_b.html">' .
                '<frame name="c" src="frame_c.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.frames/'             => $frameset,
                'http://site.with.frames/frame_a.html' => 'A frame',
                'http://site.with.frames/frame_b.html' => 'B frame',
                'http://site.with.frames/frame_c.html' => 'C frame')));
        $browser->get('http://site.with.frames/');
        $browser->setFrameFocus('a');
        $this->assertEqual($browser->getContent(), 'A frame');
        $browser->setFrameFocus('b');
        $this->assertEqual($browser->getContent(), 'B frame');
        $browser->setFrameFocus('c');
        $this->assertEqual($browser->getContent(), 'C frame');
    }

    public function testFramesetWithSomeNamedFrames()
    {
        $frameset = '<frameset>' .
                '<frame name="a" src="frame_a.html">' .
                '<frame src="frame_b.html">' .
                '<frame name="c" src="frame_c.html">' .
                '<frame src="frame_d.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.frames/'             => $frameset,
                'http://site.with.frames/frame_a.html' => 'A frame',
                'http://site.with.frames/frame_b.html' => 'B frame',
                'http://site.with.frames/frame_c.html' => 'C frame',
                'http://site.with.frames/frame_d.html' => 'D frame')));
        $this->assertEqual(
                $browser->get('http://site.with.frames/'),
                'A frameB frameC frameD frame');
        $this->assertIdentical($browser->getFrames(), array(
                'a' => 'http://site.with.frames/frame_a.html',
                2   => 'http://site.with.frames/frame_b.html',
                'c' => 'http://site.with.frames/frame_c.html',
                4   => 'http://site.with.frames/frame_d.html'));
    }

    public function testFrameFocusWithMixedNamesAndIndexes()
    {
        $frameset = '<frameset>' .
                '<frame name="a" src="frame_a.html">' .
                '<frame src="frame_b.html">' .
                '<frame name="c" src="frame_c.html">' .
                '<frame src="frame_d.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.frames/'             => $frameset,
                'http://site.with.frames/frame_a.html' => 'A frame',
                'http://site.with.frames/frame_b.html' => 'B frame',
                'http://site.with.frames/frame_c.html' => 'C frame',
                'http://site.with.frames/frame_d.html' => 'D frame')));
        $browser->get('http://site.with.frames/');
        $browser->setFrameFocus('a');
        $this->assertEqual($browser->getContent(), 'A frame');
        $browser->setFrameFocus(2);
        $this->assertEqual($browser->getContent(), 'B frame');
        $browser->setFrameFocus('c');
        $this->assertEqual($browser->getContent(), 'C frame');
        $browser->setFrameFocus(4);
        $this->assertEqual($browser->getContent(), 'D frame');
        $browser->clearFrameFocus();
        $this->assertEqual($browser->getContent(), 'A frameB frameC frameD frame');
    }

    public function testNestedFrameset()
    {
        $inner = '<frameset>' .
                '<frame name="page" src="page.html">' .
                '</frameset>';
        $outer = '<frameset>' .
                '<frame name="inner" src="inner.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.nested.frame/'           => $outer,
                'http://site.with.nested.frame/inner.html' => $inner,
                'http://site.with.nested.frame/page.html'  => 'The page')));
        $this->assertEqual(
                $browser->get('http://site.with.nested.frame/'),
                'The page');
        $this->assertIdentical($browser->getFrames(), array(
                'inner' => array(
                        'page' => 'http://site.with.nested.frame/page.html')));
    }

    public function testCanNavigateToNestedFrame()
    {
        $inner = '<frameset>' .
                '<frame name="one" src="one.html">' .
                '<frame name="two" src="two.html">' .
                '</frameset>';
        $outer = '<frameset>' .
                '<frame name="inner" src="inner.html">' .
                '<frame name="three" src="three.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.nested.frames/'           => $outer,
                'http://site.with.nested.frames/inner.html' => $inner,
                'http://site.with.nested.frames/one.html'   => 'Page one',
                'http://site.with.nested.frames/two.html'   => 'Page two',
                'http://site.with.nested.frames/three.html' => 'Page three')));

        $browser->get('http://site.with.nested.frames/');
        $this->assertEqual($browser->getContent(), 'Page onePage twoPage three');

        $this->assertTrue($browser->setFrameFocus('inner'));
        $this->assertEqual($browser->getFrameFocus(), array('inner'));
        $this->assertTrue($browser->setFrameFocus('one'));
        $this->assertEqual($browser->getFrameFocus(), array('inner', 'one'));
        $this->assertEqual($browser->getContent(), 'Page one');

        $this->assertTrue($browser->setFrameFocus('two'));
        $this->assertEqual($browser->getFrameFocus(), array('inner', 'two'));
        $this->assertEqual($browser->getContent(), 'Page two');

        $browser->clearFrameFocus();
        $this->assertTrue($browser->setFrameFocus('three'));
        $this->assertEqual($browser->getFrameFocus(), array('three'));
        $this->assertEqual($browser->getContent(), 'Page three');

        $this->assertTrue($browser->setFrameFocus('inner'));
        $this->assertEqual($browser->getContent(), 'Page onePage two');
    }

    public function testCanNavigateToNestedFrameByIndex()
    {
        $inner = '<frameset>' .
                '<frame src="one.html">' .
                '<frame src="two.html">' .
                '</frameset>';
        $outer = '<frameset>' .
                '<frame src="inner.html">' .
                '<frame src="three.html">' .
                '</frameset>';
        $browser = $this->createBrowser($this->createUserAgent(array(
                'http://site.with.nested.frames/'           => $outer,
                'http://site.with.nested.frames/inner.html' => $inner,
                'http://site.with.nested.frames/one.html'   => 'Page one',
                'http://site.with.nested.frames/two.html'   => 'Page two',
                'http://site.with.nested.frames/three.html' => 'Page three')));

        $browser->get('http://site.with.nested.frames/');
        $this->assertEqual($browser->getContent(), 'Page onePage twoPage three');

        $this->assertTrue($browser->setFrameFocusByIndex(1));
        $this->assertEqual($browser->getFrameFocus(), array(1));
        $this->assertTrue($browser->setFrameFocusByIndex(1));
        $this->assertEqual($browser->getFrameFocus(), array(1, 1));
        $this->assertEqual($browser->getContent(), 'Page one');

        $this->assertTrue($browser->setFrameFocusByIndex(2));
        $this->assertEqual($browser->getFrameFocus(), array(1, 2));
        $this->assertEqual($browser->getContent(), 'Page two');

        $browser->clearFrameFocus();
        $this->assertTrue($browser->setFrameFocusByIndex(2));
        $this->assertEqual($browser->getFrameFocus(), array(2));
        $this->assertEqual($browser->getContent(), 'Page three');

        $this->assertTrue($browser->setFrameFocusByIndex(1));
        $this->assertEqual($browser->getContent(), 'Page onePage two');
    }
}
