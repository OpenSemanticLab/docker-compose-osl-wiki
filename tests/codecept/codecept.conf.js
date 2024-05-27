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
        "name": 'selenoid',
        "enabled": true,
        "deletePassed": false,
        "autoCreate": false,
        "autoStart": true,
        "sessionTimeout": '60m',
        "enableVideo": true,
        "enableLog": true,
        "enableVNC": true,
        "screenResolution": "1280x1024x24",
      },
    },
    "helpers": {
      "WebDriver": {
        "url": process.env.MW_SITE_SERVER,
        "browser": "firefox",
        //"restart": false, // video renaming of selenoid does not work if false
        //"host": "firefox",
        "host": "localhost", //'selenoid' does not work here
        "port": 4444,
        "keepCookies": true,
        "smartWait": 10000,
        desiredCapabilities: {
          chromeOptions: {
            args: [ "--kiosk" ]
          },
          firefoxOptions: {
            args: [ "--kiosk" ]
          }
        }
        //"windowSize": "1280x1024",
        //"video": true,
        //"keepVideoForPassedTests": true,
        //"recordVideo": {},
        //"trace": true,
        //"keepTraceForPassedTests": false,
        //"selenoid:options": {
        //  "enableVNC": true,
        //  "screenResolution": "1280x1024x24"
        //"videoName": "Test"
        //}
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
            "url": process.env.MW_SITE_SERVER,
            "browser": "chrome",
            "host": "chrome",
            "restart": false
          }
        ]
      }
    }
  }