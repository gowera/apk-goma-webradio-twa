package com.gomawebradio.app.twa;

import android.os.Bundle;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.net.Uri;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.WebResourceRequest;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import android.Manifest;
import android.os.Build;
import androidx.core.content.ContextCompat;
import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.webkit.WebViewCompat;
import androidx.webkit.WebViewFeature;
import android.graphics.Color;
import android.view.Window;
import android.view.WindowManager;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.Arrays;
import java.util.HashSet;
import java.util.Set;

public class MainActivity extends AppCompatActivity {
    private WebView webView;
    private ActivityResultLauncher<String> notificationPermissionLauncher;
    private static final String APP_URL = "https://app.gomawebradio.com";
    private static final Set<String> TRUSTED_HOSTS = new HashSet<>(Arrays.asList(
            "app.gomawebradio.com",
            "gomawebradio.com"));

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        notificationPermissionLauncher = registerForActivityResult(
                new ActivityResultContracts.RequestPermission(),
                this::notifyWebNotificationPermission);
        setContentView(R.layout.activity_main);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content), (view, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            view.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });

        webView = findViewById(R.id.webview);
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        installTrustedWebMessages();
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (!request.isForMainFrame()) {
                    return false;
                }
                Uri uri = request.getUrl();
                if (isTrustedUrl(uri)) {
                    return false;
                }

                String scheme = uri.getScheme();
                if ("https".equalsIgnoreCase(scheme)
                        || "http".equalsIgnoreCase(scheme)
                        || "mailto".equalsIgnoreCase(scheme)
                        || "tel".equalsIgnoreCase(scheme)) {
                    try {
                        startActivity(new Intent(Intent.ACTION_VIEW, uri));
                    } catch (ActivityNotFoundException ignored) {
                        // No app can handle this external link.
                    }
                }
                return true;
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                if (!isTrustedUrl(Uri.parse(url))) {
                    return;
                }
                view.evaluateJavascript(
                    "(function() { " +
                    "  var observer = new MutationObserver(function(mutations) { " +
                    "    var meta = document.querySelector('meta[name=\"theme-color\"]'); " +
                    "    if (meta && window.GomaAndroid) { " +
                    "      window.GomaAndroid.postMessage(JSON.stringify({action:'status',color:meta.content})); " +
                    "    } " +
                    "  }); " +
                    "  observer.observe(document.head, { childList: true, subtree: true, attributes: true }); " +
                    "})();", null);
            }
        });
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (webView != null && webView.canGoBack()) {
                    webView.goBack();
                    return;
                }
                setEnabled(false);
                getOnBackPressedDispatcher().onBackPressed();
            }
        });
        webView.loadUrl(APP_URL);
    }

    private void installTrustedWebMessages() {
        if (!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) {
            return;
        }

        Set<String> allowedOrigins = new HashSet<>(Arrays.asList(
                "https://app.gomawebradio.com",
                "https://gomawebradio.com"));
        WebViewCompat.addWebMessageListener(webView, "GomaAndroid", allowedOrigins,
                (view, message, sourceOrigin, isMainFrame, replyProxy) -> {
                    if (!isMainFrame) {
                        return;
                    }
                    try {
                        JSONObject payload = new JSONObject(message.getData());
                        String action = payload.optString("action");
                        if ("request-notification-permission".equals(action)) {
                            requestNotificationPermission();
                        } else if ("status".equals(action)) {
                            updateStatusBarColor(payload.optString("color"));
                        }
                    } catch (JSONException ignored) {
                        // Ignore malformed messages from the web page.
                    }
                });
    }

    private boolean isTrustedUrl(Uri uri) {
        return "https".equalsIgnoreCase(uri.getScheme())
                && TRUSTED_HOSTS.contains(uri.getHost() == null ? "" : uri.getHost().toLowerCase());
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                    != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS);
                return;
            }
        }
        notifyWebNotificationPermission(true);
    }

    private void notifyWebNotificationPermission(boolean granted) {
        if (webView != null) {
            webView.evaluateJavascript(
                    "window.dispatchEvent(new CustomEvent('gw-native-notification-permission',{detail:{granted:"
                            + granted + "}}));",
                    null);
        }
    }

    private void updateStatusBarColor(String colorCode) {
        try {
            int color = Color.parseColor(colorCode);
            Window window = getWindow();
            window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
            window.setStatusBarColor(color);
        } catch (IllegalArgumentException ignored) {
            // Ignore invalid theme colors.
        }
    }
}
