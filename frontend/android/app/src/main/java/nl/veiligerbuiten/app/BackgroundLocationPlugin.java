package nl.veiligerbuiten.app;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import androidx.core.content.ContextCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

/** Background access is requested separately, after the in-app disclosure. */
@CapacitorPlugin(name = "BackgroundLocation", permissions = {
    @Permission(alias = "background", strings = { Manifest.permission.ACCESS_BACKGROUND_LOCATION })
})
public class BackgroundLocationPlugin extends Plugin {
    private boolean hasForeground() {
        return ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
            || ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    private JSObject status() {
        boolean granted = hasForeground() && (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q
            || ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_BACKGROUND_LOCATION) == PackageManager.PERMISSION_GRANTED);
        JSObject result = new JSObject();
        result.put("granted", granted);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            result.put("optionLabel", getContext().getPackageManager().getBackgroundPermissionOptionLabel().toString());
        }
        return result;
    }

    @PluginMethod
    public void check(PluginCall call) {
        call.resolve(status());
    }

    @PluginMethod
    public void request(PluginCall call) {
        if (!hasForeground()) {
            call.reject("Allow foreground location first.");
            return;
        }
        if (status().optBoolean("granted")) {
            call.resolve(status());
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            // Android 11+ exposes 'Allow all the time' in Settings, not the foreground prompt.
            Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:" + getContext().getPackageName()));
            getActivity().startActivity(intent);
            call.resolve(status());
        } else {
            requestPermissionForAlias("background", call, "backgroundResult");
        }
    }

    @PermissionCallback
    private void backgroundResult(PluginCall call) {
        call.resolve(status());
    }
}
