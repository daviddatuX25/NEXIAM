/**
 * NEXIAM Local Print Queue & Document Cache Service
 * 
 * Powered by browser-native IndexedDB.
 * Caches generated PDF blobs locally on the staff member's device.
 * Prevents repetitive, wasteful server re-rendering when closing modals
 * or navigating between pages.
 */

const DB_NAME = 'nexiam_print_cache_v1';
const STORE_NAME = 'print_jobs';
const DB_VERSION = 1;

let dbPromise = null;

function openDB() {
  if (typeof window === 'undefined' || !window.indexedDB) {
    return Promise.resolve(null);
  }

  if (dbPromise) return dbPromise;

  dbPromise = new Promise((resolve, reject) => {
    const request = window.indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
        store.createIndex('cache_key', 'cache_key', { unique: false });
        store.createIndex('created_at', 'created_at', { unique: false });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => {
      console.warn('Failed to open NEXIAM IndexedDB print cache:', request.error);
      resolve(null);
    };
  });

  return dbPromise;
}

/**
 * Save a generated document print job into local IndexedDB
 */
export async function savePrintJob({
  id = null,
  userId = null,
  actorId = null,
  institutionId = null,
  cacheKey = '',
  title = 'Document',
  surfaceKey = '',
  filename = 'document.pdf',
  blob = null,
  grain = 'cycle_program',
  pageCount = 1,
}) {
  if (!blob || !(blob instanceof Blob)) return null;

  const db = await openDB();
  if (!db) return null;

  const jobId = id || `job_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`;
  const record = {
    id: jobId,
    user_id: userId,
    actor_id: actorId,
    institution_id: institutionId,
    cache_key: cacheKey,
    title,
    surface_key: surfaceKey,
    filename,
    blob,
    grain,
    page_count: pageCount,
    size_bytes: blob.size,
    status: 'ready',
    created_at: new Date().toISOString(),
    printed_at: null,
  };

  return new Promise((resolve) => {
    try {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      store.put(record);
      tx.oncomplete = () => {
        dispatchQueueUpdate();
        resolve(record);
      };
      tx.onerror = () => resolve(null);
    } catch (e) {
      console.warn('Error saving to IndexedDB print queue:', e);
      resolve(null);
    }
  });
}

/**
 * Find a cached print job matching an active cacheKey
 */
export async function getCachedJob(cacheKey, userId = null) {
  if (!cacheKey) return null;

  const db = await openDB();
  if (!db) return null;

  return new Promise((resolve) => {
    try {
      const tx = db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('cache_key');
      const request = index.getAll(cacheKey);

      request.onsuccess = () => {
        let matches = request.result || [];
        if (userId !== null) {
          matches = matches.filter((j) => !j.user_id || j.user_id === userId);
        }
        const dayAgo = Date.now() - 24 * 60 * 60 * 1000;
        matches = matches.filter((j) => new Date(j.created_at).getTime() > dayAgo);

        if (matches.length === 0) {
          resolve(null);
          return;
        }
        matches.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        resolve(matches[0]);
      };
      request.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}

/**
 * Retrieve all locally cached print jobs
 */
export async function getAllPrintJobs(actorId = null) {
  const db = await openDB();
  if (!db) return [];

  return new Promise((resolve) => {
    try {
      const tx = db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const request = store.getAll();

      request.onsuccess = () => {
        let jobs = request.result || [];
        if (actorId !== null && actorId !== undefined) {
          jobs = jobs.filter((j) => !j.actor_id || String(j.actor_id) === String(actorId));
        }
        const dayAgo = Date.now() - 24 * 60 * 60 * 1000;
        jobs = jobs.filter((j) => new Date(j.created_at).getTime() > dayAgo);
        jobs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        resolve(jobs);
      };
      request.onerror = () => resolve([]);
    } catch {
      resolve([]);
    }
  });
}

/**
 * Mark a print job as physically printed
 */
export async function markJobPrinted(id) {
  const db = await openDB();
  if (!db) return;

  try {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const getReq = store.get(id);

    getReq.onsuccess = () => {
      const job = getReq.result;
      if (job) {
        job.status = 'printed';
        job.printed_at = new Date().toISOString();
        store.put(job);
      }
    };
    tx.oncomplete = () => dispatchQueueUpdate();
  } catch (e) {
    console.warn('Error marking job printed in IndexedDB:', e);
  }
}

/**
 * Delete a specific print job by id
 */
export async function deletePrintJob(id) {
  const db = await openDB();
  if (!db) return;

  try {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.delete(id);
    tx.oncomplete = () => dispatchQueueUpdate();
  } catch (e) {
    console.warn('Error deleting job from IndexedDB:', e);
  }
}

/**
 * Clear all cached print jobs
 */
export async function clearAllPrintJobs() {
  const db = await openDB();
  if (!db) return;

  try {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.clear();
    tx.oncomplete = () => dispatchQueueUpdate();
  } catch (e) {
    console.warn('Error clearing IndexedDB print queue:', e);
  }
}

/**
 * Clear cached print jobs belonging to a specific actor
 */
export async function clearUserPrintJobs(actorId) {
  if (actorId === null || actorId === undefined) return;

  const db = await openDB();
  if (!db) return;

  try {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const request = store.getAll();

    request.onsuccess = () => {
      for (const job of request.result || []) {
        if (job.actor_id && String(job.actor_id) === String(actorId)) {
          store.delete(job.id);
        }
      }
    };
    tx.oncomplete = () => dispatchQueueUpdate();
  } catch (e) {
    console.warn('Error clearing actor print jobs from IndexedDB:', e);
  }
}

function dispatchQueueUpdate() {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('nexiam-print-queue-updated'));
  }
}
