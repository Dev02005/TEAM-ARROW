import React from 'react';

const defaultProps = {
  width: 20,
  height: 20,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': 'true',
  style: { verticalAlign: 'middle', display: 'inline-block' }
};

export const PillIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z" />
    <path d="m8.5 8.5 7 7" />
  </svg>
);

export const MedicalCrossIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M12 4v16m-8-8h16" strokeWidth={2.5} />
  </svg>
);

export const HomeIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
    <polyline points="9 22 9 12 15 12 15 22" />
  </svg>
);

export const BookOpenIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
  </svg>
);

export const SearchIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <circle cx="11" cy="11" r="8" />
    <path d="m21 21-4.3-4.3" />
  </svg>
);

export const UsersIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
    <circle cx="9" cy="7" r="4" />
    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
  </svg>
);

export const UserIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
    <circle cx="12" cy="7" r="4" />
  </svg>
);

export const UserPlusIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
    <circle cx="9" cy="7" r="4" />
    <line x1="19" y1="8" x2="19" y2="14" />
    <line x1="22" y1="11" x2="16" y2="11" />
  </svg>
);

export const KeyIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="m21 2-2 2m-1.5 1.5L14 9l-3-3-2 2 3 3-2 2-3-3-3 3a5.5 5.5 0 0 0 7.78 7.78l8.72-8.72A2 2 0 0 0 23 4.5V2z" />
  </svg>
);

export const LogInIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
    <polyline points="10 17 15 12 10 7" />
    <line x1="15" y1="12" x2="3" y2="12" />
  </svg>
);

export const LogOutIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
    <polyline points="16 17 21 12 16 7" />
    <line x1="21" y1="12" x2="9" y2="12" />
  </svg>
);

export const GlobeIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <circle cx="12" cy="12" r="10" />
    <line x1="2" y1="12" x2="22" y2="12" />
    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
  </svg>
);

export const Volume2Icon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
    <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
    <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
  </svg>
);

export const CameraIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
    <circle cx="12" cy="13" r="3" />
  </svg>
);

export const PrinterIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <polyline points="6 9 6 2 18 2 18 9" />
    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
    <rect x="6" y="14" width="12" height="8" />
  </svg>
);

export const AlertTriangleIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || '#ea580c'} {...props}>
    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
    <line x1="12" y1="9" x2="12" y2="13" />
    <line x1="12" y1="17" x2="12.01" y2="17" />
  </svg>
);

export const AlertOctagonIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || '#dc2626'} {...props}>
    <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2" />
    <line x1="12" y1="8" x2="12" y2="12" />
    <line x1="12" y1="16" x2="12.01" y2="16" />
  </svg>
);

export const AlertCircleIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || '#ca8a04'} {...props}>
    <circle cx="12" cy="12" r="10" />
    <line x1="12" y1="8" x2="12" y2="12" />
    <line x1="12" y1="16" x2="12.01" y2="16" />
  </svg>
);

export const InfoIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || '#2563eb'} {...props}>
    <circle cx="12" cy="12" r="10" />
    <line x1="12" y1="16" x2="12" y2="12" />
    <line x1="12" y1="8" x2="12.01" y2="8" />
  </svg>
);

export const CheckCircleIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || '#16a34a'} {...props}>
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
    <polyline points="22 4 12 14.01 9 11.01" />
  </svg>
);

export const CheckIcon = ({ size = 18, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} strokeWidth={2.5} {...props}>
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

export const KidneyIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M12 2C8 2 4 5 4 10c0 4 2 8 8 12 6-4 8-8 8-12 0-5-4-8-8-8Z" />
    <circle cx="12" cy="10" r="3" />
  </svg>
);

export const LungsIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M12 4v16" />
    <path d="M12 8c-3-2-7-1-8 3-1 4 0 7 2 9 2 2 5 0 6-3" />
    <path d="M12 8c3-2 7-1 8 3 1 4 0 7-2 9-2 2-5 0-6-3" />
  </svg>
);

export const HeartIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
  </svg>
);

export const BabyIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <circle cx="12" cy="12" r="9" />
    <path d="M9 10h.01M15 10h.01" />
    <path d="M9.5 15a3.5 3.5 0 0 0 5 0" />
  </svg>
);

export const ShieldAlertIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    <line x1="12" y1="8" x2="12" y2="12" />
    <line x1="12" y1="16" x2="12.01" y2="16" />
  </svg>
);

export const ShieldCheckIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    <polyline points="9 12 11 14 15 10" />
  </svg>
);

export const ClockIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <circle cx="12" cy="12" r="10" />
    <polyline points="12 6 12 12 16 14" />
  </svg>
);

export const CalendarIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
    <line x1="16" y1="2" x2="16" y2="6" />
    <line x1="8" y1="2" x2="8" y2="6" />
    <line x1="3" y1="10" x2="21" y2="10" />
  </svg>
);

export const ZapIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
  </svg>
);

export const TargetIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <circle cx="12" cy="12" r="10" />
    <circle cx="12" cy="12" r="6" />
    <circle cx="12" cy="12" r="2" />
  </svg>
);

export const ChevronDownIcon = ({ size = 14, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

export const LightbulbIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-1 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" />
    <path d="M9 18h6" />
    <path d="M10 22h4" />
  </svg>
);

export const StethoscopeIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M4.5 3v5a5.5 5.5 0 0 0 11 0V3" />
    <path d="M2 3h5" />
    <path d="M13 3h5" />
    <path d="M10 13.5v3.5a4 4 0 0 0 8 0V15" />
    <circle cx="18" cy="15" r="2" />
  </svg>
);

export const ClipboardListIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
    <path d="M12 11h4" />
    <path d="M12 16h4" />
    <path d="M8 11h.01" />
    <path d="M8 16h.01" />
  </svg>
);

export const PhoneCallIcon = ({ size = 20, className = '', color, ...props }) => (
  <svg {...defaultProps} width={size} height={size} className={className} stroke={color || 'currentColor'} {...props}>
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
  </svg>
);

export function getSeverityIcon(severity, size = 18) {
  const sev = (severity || '').toLowerCase();
  if (sev === 'contraindicated' || sev === 'critical' || sev === 'danger') {
    return <AlertOctagonIcon size={size} color="#dc2626" />;
  }
  if (sev === 'major' || sev === 'high' || sev === 'serious') {
    return <AlertTriangleIcon size={size} color="#ea580c" />;
  }
  if (sev === 'moderate' || sev === 'caution') {
    return <AlertCircleIcon size={size} color="#d97706" />;
  }
  if (sev === 'clean' || sev === 'safe') {
    return <CheckCircleIcon size={size} color="#16a34a" />;
  }
  return <InfoIcon size={size} color="#2563eb" />;
}
