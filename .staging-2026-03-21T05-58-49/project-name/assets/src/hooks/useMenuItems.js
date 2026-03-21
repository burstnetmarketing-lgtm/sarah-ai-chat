import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '../api/client.js';
import logger from '../utils/logger.js';

export function useMenuItems() {
  const [parents, setParents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async (showLoading = true) => {
    try {
      if (showLoading) setLoading(true);
      const res = await apiFetch('menu-items');
      if (res.success) setParents(res.data.parents);
    } catch (err) {
      logger.error('useMenuItems', 'Failed to load menu items', { err: err.message });
    } finally {
      if (showLoading) setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function act(action) {
    setSaving(true);
    try {
      await action();
      await load(false);
    } catch (err) {
      logger.error('useMenuItems', 'Action failed', { err: err.message });
    } finally {
      setSaving(false);
    }
  }

  return {
    parents,
    loading,
    saving,
    createParent: (data) => act(() => apiFetch('menu-items', 'POST', data)),
    createChild:  (data) => act(() => apiFetch('menu-items', 'POST', data)),
    toggle:       (key)  => act(() => apiFetch(`menu-items/${key}/toggle`, 'POST')),
    remove:       (key)  => act(() => apiFetch(`menu-items/${key}`, 'DELETE')),
    moveUp:       (key)  => act(() => apiFetch(`menu-items/${key}/move`, 'POST', { direction: 'up' })),
    moveDown:     (key)  => act(() => apiFetch(`menu-items/${key}/move`, 'POST', { direction: 'down' })),
  };
}
