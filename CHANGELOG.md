<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

## [0.9.0-alpha.2](https://github.com/merakiframework/http-router/compare/v0.9.0-alpha.1...v0.9.0-alpha.2) (2026-06-08)

### Bug Fixes


##### Router

* Namespace boundary is method agnostic ([af18f1](https://github.com/merakiframework/http-router/commit/af18f1ea965f11f9e25ae3aff2ba933646008d03))


---

## [0.9.0-alpha.1](https://github.com/merakiframework/http-router/compare/v0.8.0...v0.9.0-alpha.1) (2026-05-30)

### ⚠ BREAKING CHANGES

* Change php version to 8.1 ([5f7c32](https://github.com/merakiframework/http-router/commit/5f7c3246a32dab31f22041cd4c55a6b911b8fd0a))
* This library is no longer compatible with php version 8. Use php version 8.1+ ([5f7c32](https://github.com/merakiframework/http-router/commit/5f7c3246a32dab31f22041cd4c55a6b911b8fd0a))

### Features

* Add example showcasing how signature matching on resources is method-specific ([0d0b38](https://github.com/merakiframework/http-router/commit/0d0b3898354f63e259026d7c159f9e8a368f521e))
* Add support for array types [#10](https://github.com/merakiframework/http-router/issues/10) ([b9c804](https://github.com/merakiframework/http-router/commit/b9c804cfb338d710b83e09befe4cd32438e03e29))
* Allow for type hinting of 'floats' in route parameters [#11](https://github.com/merakiframework/http-router/issues/11) ([aaa98e](https://github.com/merakiframework/http-router/commit/aaa98e85a784bcc8ad98008cdfb38cb28ebcf4e5))
* Factor out complex configuration logic as it is no longer needed ([cad926](https://github.com/merakiframework/http-router/commit/cad926537ab2d7f7a7d08d156ea37cda7ec12f2f))
* Provide a way to exclude words from singular/plural translations [#17](https://github.com/merakiframework/http-router/issues/17) ([af927d](https://github.com/merakiframework/http-router/commit/af927d4c4b0c23d6873651e4b41be66acab84dbd))
* Routes no have a type to better distinguish its intended purpose ([f54bd6](https://github.com/merakiframework/http-router/commit/f54bd6362ae115d342e1cfa412eb29ceaf795925))
* Separate type validation and casting into a scalar object ([1c0340](https://github.com/merakiframework/http-router/commit/1c03400dbd5e60d0e4ad7d9ace539748e566f41f))

##### Config

* Make supportedMethods configurable for WebDAV and HTTP extensions ([80b32a](https://github.com/merakiframework/http-router/commit/80b32ad76a4baa24b53a4bbd58748b370d98ea6c))

##### Router

* Action routes are standalone — params are strictly trailing ([488b61](https://github.com/merakiframework/http-router/commit/488b616c0fc8e689e5ddb8fea96b9a86170d0ed5))
* Auto-synthesise OPTIONS responses listing allowed methods ([ebbb78](https://github.com/merakiframework/http-router/commit/ebbb7817be152029c3538ae34fb99d1e4b15ec42))
* Enum, UUID & segment-consuming value-object casters ([d88a9e](https://github.com/merakiframework/http-router/commit/d88a9eb5bda008b8a73e91a81575bb0e58ddab4a))

### Bug Fixes

* Add missing static analysis docs ([c7b3dd](https://github.com/merakiframework/http-router/commit/c7b3dd024127f84a6f16b755997823fbb4931a06))
* All tests no run under latest PHPUnit ([03644f](https://github.com/merakiframework/http-router/commit/03644ff20215d5cdbce377772112335f516e25f8))
* Compound words default to GetAction; sub-resource routes use GetAction not GetOneAction; add withInflectionRule() ([e767b3](https://github.com/merakiframework/http-router/commit/e767b3dfbbce0af85ecb0d39a991fd5a7878378c))
* Incorrect parameter types returning 400 instead of 422 ([81464f](https://github.com/merakiframework/http-router/commit/81464f16738f3018c8156854ce5471c8344aeae4))
* Prefer more specific (deeper) routes over less specific ones ([bf50b5](https://github.com/merakiframework/http-router/commit/bf50b5b2d30eccd7db93cb300e56a9d56e15f9bf))
* Remove code still reliant on symfony/inflector ([714e9d](https://github.com/merakiframework/http-router/commit/714e9d2f850f1d3ec5de062f5d9c59badfb5d313))
* Remove the unnecessary interpolation to stop analysis errors ([419046](https://github.com/merakiframework/http-router/commit/4190467f4de4dc8e858de99733c6a47a30ab8bd7))
* Replace narrowspark/http-emitter with laminas/laminas-httphandlerrunner ([4402a9](https://github.com/merakiframework/http-router/commit/4402a982582be9edb8cd625a62003945179c7496))
* Resolve all psalm errors for PHP 8.4 compatibility ([4df6d4](https://github.com/merakiframework/http-router/commit/4df6d4eba7d6c49b4a5020577d529b2464f0ca48)) *[*[*@psalm-api*](https://github.com/psalm-api), [*@psalm-suppress*](https://github.com/psalm-suppress), [*@psalm-mutable*](https://github.com/psalm-mutable)*]*
* Resolve PHP 8.4 and PHPUnit deprecations ([af5600](https://github.com/merakiframework/http-router/commit/af560011cc574c91250e6b69246bd477fde634b8))
* Signature mismatch on restful routes should throw an exception not 404 ([fbe91f](https://github.com/merakiframework/http-router/commit/fbe91f71548d2c05f1ec4bfa2714bfeef1250a91))
* Wrong url reflection for 'static' routes ([5748e2](https://github.com/merakiframework/http-router/commit/5748e20c315f3e04033ca732eeadbc88ad8c3ea9))

##### Release

* Vendor/bin file path not resolving correctly ([2cbe08](https://github.com/merakiframework/http-router/commit/2cbe089b8fd8601115cd383aeb632214303abc96))

##### Request Target

* Strip trailing slashes from request paths ([58ca79](https://github.com/merakiframework/http-router/commit/58ca7983bbd8e28fe49ceaaa998d0909432117e1))

##### Router

* Drop CONNECT/TRACE and add positive tests for write methods ([33d1dd](https://github.com/merakiframework/http-router/commit/33d1ddead127f2079bfc68fcef2741ac080a5611))
* Reject URLs that don't match a handler's signature ([e11983](https://github.com/merakiframework/http-router/commit/e11983d3610228d18e3745ac65e27809d58db791))

##### String Type

* CastToInt accepts leading zeros ([952774](https://github.com/merakiframework/http-router/commit/9527746885bc8827fe5974a4c4597e2b692960bf))


---

## [0.8.0](https://github.com/merakiframework/http-router/compare/813b193033a4a12759b1f286deadd65b67342f42...v0.8.0) (2022-12-30)

### Features

* Initial functionality ([15909e](https://github.com/merakiframework/http-router/commit/15909eb46872adde274fa3c03d843ab030db8ffe))


---

