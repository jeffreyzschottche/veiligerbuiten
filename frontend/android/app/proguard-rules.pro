# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# If your project uses WebView with JS, uncomment the following
# and specify the fully qualified class name to the JavaScript interface
# class:
#-keepclassmembers class fqcn.of.javascript.interface.for.webview {
#   public *;
#}

# Uncomment this to preserve the line number information for
# debugging stack traces.
#-keepattributes SourceFile,LineNumberTable

# If you keep the line number information, uncomment this to
# hide the original source file name.
#-renamesourcefileattribute SourceFile

# BackgroundRunner's JNI library looks these classes and their members up by
# their original names. The bundled AAR has no consumer rules; shrinking removes
# NativeWebAPI and renames its argument/return types, causing a native abort at
# startup (ClassNotFoundException in Runner.initRunner).
-keep class io.ionic.android_js_engine.** { *; }

# Capacitor reads plugin permissions and callbacks through runtime annotations.
# Keep the annotation types as well as the plugin classes kept by Capacitor.
-keep @interface com.getcapacitor.** { *; }
-keepattributes RuntimeVisibleAnnotations,RuntimeInvisibleAnnotations,AnnotationDefault
