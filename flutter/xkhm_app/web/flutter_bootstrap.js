// Flutter web bootstrap for the xkhm application.
//
// The default Flutter bootstrap loads CanvasKit from the gstatic CDN. That
// would break the strict Content-Security-Policy of the xkhm deployment and
// would make the application depend on an external host at runtime.
//
// Pointing `canvasKitBaseUrl` at the locally bundled copy keeps everything
// same-origin, which also means the session cookie works without CORS.
{{flutter_js}}
{{flutter_build_config}}

_flutter.loader.load({
  config: {
    canvasKitBaseUrl: "canvaskit/",
  },
});
