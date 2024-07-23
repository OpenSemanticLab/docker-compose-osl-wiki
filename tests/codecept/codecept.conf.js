exports.config = {
  "tests": "./tests/*_test.js",
  "timeout": 6000,
  "output": "./output",
  "plugins": {
    "pauseOnFail": {
      "enabled": false,
    },
    "autoDelay": {
      "enabled": true
    },
    "selenoid": {
      "name": 'selenoid',
      "enabled": true,
      "deletePassed": false,
      "autoCreate": false,
      "autoStart": false,
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
      "host": "selenoid", //'selenoid' does not work here
      "port": 4444,
      "keepCookies": true,
      "smartWait": 10000,
      desiredCapabilities: {
        // https://stackoverflow.com/questions/24507078/how-to-deal-with-certificates-using-selenium
        acceptInsecureCerts: true,
        chromeOptions: {
          // args: ["--kiosk"] // note: use kiosk mode for demo videos
        },
        firefoxOptions: {
          // args: ["--kiosk"] // note: use kiosk mode for demo videos
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
        {
          "url": process.env.MW_SITE_SERVER,
          "browser": "firefox",
          "host": "localhost", //'selenoid' does not work here
          "port": 4444,
          "keepCookies": true,
          "smartWait": 10000,
          desiredCapabilities: {
            firefoxOptions: {
              // args: ["--kiosk"] // hide address field
            }
          }
        },
        {
          "url": process.env.MW_SITE_SERVER,
          "browser": "chrome",
          "host": "localhost", //'selenoid' does not work here
          "port": 4444,
          "keepCookies": true,
          "smartWait": 10000,
          desiredCapabilities: {
            chromeOptions: {
              args: ["--kiosk"]
            }
          }
        },
        {
          "url": process.env.MW_SITE_SERVER,
          "browser": "webkit",
          "host": "localhost", //'selenoid' does not work here
          "port": 4444,
          "keepCookies": true,
          "smartWait": 10000,
          desiredCapabilities: {
            webkitOptions: {
              args: ["--kiosk"]
            }
          }
        },
      ]
    }
  }
}