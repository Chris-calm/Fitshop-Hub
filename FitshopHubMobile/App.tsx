/**
 * Sample React Native App
 * https://github.com/facebook/react-native
 *
 * @format
 */

import React, { useCallback, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Platform,
  Pressable,
  StatusBar,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { WebView } from 'react-native-webview';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

const SITE_URL = 'https://fitshop-hub.vercel.app';
const LATEST_URL = 'https://fitshop-hub.vercel.app/assets/apk/latest.json';

// Keep this in sync with android/app/build.gradle (versionCode)
const APP_VERSION_CODE = 1;

function App() {
  const webRef = useRef<WebView>(null);
  const [loading, setLoading] = useState(true);
  const [checkingUpdate, setCheckingUpdate] = useState(false);

  const userAgent = useMemo(() => {
    const base = Platform.OS === 'android' ? 'Android' : Platform.OS;
    return `FitshopHubMobile/${APP_VERSION_CODE} (${base})`;
  }, []);

  const checkForUpdates = useCallback(async () => {
    if (checkingUpdate) return;
    setCheckingUpdate(true);
    try {
      const url = `${LATEST_URL}${LATEST_URL.includes('?') ? '&' : '?'}t=${Date.now()}`;
      const resp = await fetch(url, {
        headers: {
          'Cache-Control': 'no-cache',
          Pragma: 'no-cache',
        },
      });
      if (!resp.ok) {
        throw new Error('Unable to check updates right now.');
      }
      const data = await resp.json();
      const latestCode = Number(data?.versionCode ?? 0);
      const apkUrl = String(data?.apkUrl ?? '');
      const notes = String(data?.releaseNotes ?? '');

      if (!latestCode || !apkUrl) {
        throw new Error('Update info is invalid.');
      }

      if (latestCode <= APP_VERSION_CODE) {
        Alert.alert('Up to date', 'You already have the latest version.');
        return;
      }

      Alert.alert(
        'Update available',
        `${notes ? notes + '\n\n' : ''}Open download page?`,
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Download',
            onPress: async () => {
              const ok = await Linking.canOpenURL(apkUrl);
              if (!ok) {
                Alert.alert('Error', 'Cannot open download link.');
                return;
              }
              await Linking.openURL(apkUrl);
            },
          },
        ],
      );
    } catch (e: any) {
      Alert.alert('Update check failed', String(e?.message ?? e));
    } finally {
      setCheckingUpdate(false);
    }
  }, [checkingUpdate]);

  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.safe}>
        <StatusBar barStyle="light-content" />
        <View style={styles.topbar}>
          <Text style={styles.brand}>Fitshop Hub</Text>
          <View style={styles.topActions}>
            <Pressable
              onPress={() => webRef.current?.reload()}
              style={({ pressed }) => [styles.actionBtn, pressed && styles.actionBtnPressed]}
            >
              <Text style={styles.actionText}>Reload</Text>
            </Pressable>
            <Pressable
              onPress={checkForUpdates}
              disabled={checkingUpdate}
              style={({ pressed }) => [
                styles.actionBtn,
                pressed && styles.actionBtnPressed,
                checkingUpdate && styles.actionBtnDisabled,
              ]}
            >
              <Text style={styles.actionText}>{checkingUpdate ? 'Checking…' : 'Check updates'}</Text>
            </Pressable>
          </View>
        </View>

        <View style={styles.webWrap}>
          <WebView
            ref={webRef}
            source={{ uri: SITE_URL }}
            onLoadStart={() => setLoading(true)}
            onLoadEnd={() => setLoading(false)}
            userAgent={userAgent}
            javaScriptEnabled
            domStorageEnabled
            allowsBackForwardNavigationGestures
            setSupportMultipleWindows={false}
          />

          {loading ? (
            <View style={styles.loading}>
              <ActivityIndicator size="large" color="#6366F1" />
            </View>
          ) : null}
        </View>
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#000',
  },
  topbar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#0b0b0b',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#222',
  },
  brand: {
    color: '#e5e7eb',
    fontSize: 16,
    fontWeight: '700',
  },
  topActions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionBtn: {
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 10,
    backgroundColor: '#141414',
    borderWidth: 1,
    borderColor: '#262626',
  },
  actionBtnPressed: {
    opacity: 0.85,
  },
  actionBtnDisabled: {
    opacity: 0.6,
  },
  actionText: {
    color: '#e5e7eb',
    fontSize: 12,
    fontWeight: '600',
  },
  webWrap: {
    flex: 1,
  },
  loading: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.2)',
  },
});

export default App;
