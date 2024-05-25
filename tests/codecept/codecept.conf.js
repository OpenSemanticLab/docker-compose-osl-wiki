exports.config = {
    "tests": "./tests/*_test.js",
    "timeout": 6000,
    "output": "./output",
    "plugins": {
      "pauseOnFail": {},
      "autoDelay": {
        "enabled": true
      },
      "selenoid": {
        "enabled": false,
        "deletePassed": true,
        "autoCreate": true,
        "autoStart": true,
        "sessionTimeout": '60m',
        "enableVideo": true,
        "enableLog": true,
      },
    },
    "helpers": {
      "WebDriver": {
        "url": process.env.MW_SITE_SERVER,
        "browser": "firefox",
        "restart": false,
        "host": "firefox",
        "keepCookies": true,
        "smartWait": 10000
      },
      "ChaiWrapper": {
        "require": "codeceptjs-chai"
      },
      "customHelpers": {
        "require": "./helpers.js"
      }
    },
    "include": {
      "I": "./steps.js"
    },
    "bootstrap": false,
    "mocha": {},
    "name": "codeceptjs",
    "multiple": {
      "parallel": {
        "chunks": 2
      },
      "basic": {
        "browsers": [
          "firefox",
          {
            "url": "https://stacktest.digital.isc.fraunhofer.de/",
            "browser": "chrome",
            "host": "chrome",
            "restart": false
          }
        ]
      }
    }
  }