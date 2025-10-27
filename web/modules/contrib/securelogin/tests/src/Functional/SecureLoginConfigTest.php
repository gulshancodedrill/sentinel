<?php

namespace Drupal\Tests\securelogin\Functional;

/**
 * Tests secure login module configuration.
 *
 * @group Secure login
 */
class SecureLoginConfigTest extends SecureLoginTestBase {

  const BASE_URL = 'https://securelogin.test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // We cannot login to HTTP site if Secure Login is installed.
    if (!$this->isSecure) {
      $this->config('securelogin.settings')->set('base_url', self::BASE_URL)->save();
      return;
    }
    $web_user = $this->drupalCreateUser(['administer site configuration']);
    $this->assertNotEmpty($web_user);
    $this->drupalLogin($web_user);
    $this->drupalGet('admin/config/people/securelogin');
    $fields['base_url'] = self::BASE_URL;
    $this->submitForm($fields, 'Save configuration');
  }

  /**
   * Ensure redirects use the configured base URL.
   */
  public function testSecureLoginBaseUrl(): void {
    // Disable redirect following.
    if (method_exists($this->getSession()->getDriver(), 'getClient')) {
      $this->getSession()->getDriver()->getClient()->followRedirects(FALSE);
    }
    $maximumMetaRefreshCount = $this->maximumMetaRefreshCount;
    $this->maximumMetaRefreshCount = 0;
    $this->drupalGet($this->httpUrl('user/login'));
    $this->assertSession()->statusCodeEquals(301);
    $this->assertSession()->responseHeaderEquals('Location', self::BASE_URL . '/user/login');
    if (method_exists($this->getSession()->getDriver(), 'getClient')) {
      $this->getSession()->getDriver()->getClient()->followRedirects(TRUE);
    }
    $this->maximumMetaRefreshCount = $maximumMetaRefreshCount;
  }

}
