"""Apply the Windows reference client's limits before spawning Playwright children."""
import ctypes
import os
from ctypes import wintypes

_job = None

def reference_limits():
    global _job
    if os.name != 'nt':
        raise RuntimeError('Apply and verify equivalent 4 CPU/8 GiB limits on this client platform.')
    size_t = ctypes.c_size_t
    class Basic(ctypes.Structure):
        _fields_ = [('process_time', ctypes.c_longlong), ('job_time', ctypes.c_longlong), ('flags', wintypes.DWORD),
                    ('minimum_working_set', size_t), ('maximum_working_set', size_t), ('active_processes', wintypes.DWORD),
                    ('affinity', size_t), ('priority', wintypes.DWORD), ('scheduling', wintypes.DWORD)]
    class IO(ctypes.Structure):
        _fields_ = [(name, ctypes.c_ulonglong) for name in ['read_operations', 'write_operations', 'other_operations', 'read_bytes', 'write_bytes', 'other_bytes']]
    class Extended(ctypes.Structure):
        _fields_ = [('basic', Basic), ('io', IO), ('process_memory', size_t), ('job_memory', size_t), ('peak_process_memory', size_t), ('peak_job_memory', size_t)]
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    kernel.GetCurrentProcess.restype = wintypes.HANDLE
    kernel.CreateJobObjectW.argtypes = [ctypes.c_void_p, wintypes.LPCWSTR]
    kernel.CreateJobObjectW.restype = wintypes.HANDLE
    kernel.SetProcessAffinityMask.argtypes = [wintypes.HANDLE, size_t]
    kernel.SetInformationJobObject.argtypes = [wintypes.HANDLE, ctypes.c_int, ctypes.c_void_p, wintypes.DWORD]
    kernel.AssignProcessToJobObject.argtypes = [wintypes.HANDLE, wintypes.HANDLE]
    process = kernel.GetCurrentProcess()
    if not kernel.SetProcessAffinityMask(process, 0xF):
        raise ctypes.WinError(ctypes.get_last_error())
    _job = kernel.CreateJobObjectW(None, None)
    if not _job:
        raise ctypes.WinError(ctypes.get_last_error())
    limits = Extended()
    limits.basic.flags = 0x200 | 0x10  # Job-wide memory and CPU affinity, including descendants.
    limits.basic.affinity = 0xF
    limits.job_memory = 8 * 1024 ** 3
    if not kernel.SetInformationJobObject(_job, 9, ctypes.byref(limits), ctypes.sizeof(limits)) or not kernel.AssignProcessToJobObject(_job, process):
        raise ctypes.WinError(ctypes.get_last_error())
    return {'cpus': 4, 'memory_bytes': limits.job_memory, 'method': 'Windows process affinity and nested Job Object', 'applied': True}
