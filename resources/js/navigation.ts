export const schoolGroups = [
    { label: 'MAIN', items: [{ page: 'dashboard', label: 'Dashboard', icon: 'dashboard' }] },
    { label: 'ACADEMICS DEPARTMENT', items: [
        { page: 'students', label: 'Students', icon: 'students' }, { page: 'classes', label: 'Classes', icon: 'classes' },
        { page: 'sections', label: 'Sections', icon: 'classes' }, { page: 'calendar', label: 'Calendar', icon: 'calendar' }, { page: 'academics', label: 'Academic Sessions', icon: 'academics' }, { page: 'teachers', label: 'Teachers', icon: 'staff' }, { page: 'teacher-assignments', label: 'Teacher Assignments', icon: 'staff' }, { page: 'enrollments', label: 'Enrollments', icon: 'students' }, { page: 'class-subjects', label: 'Class Subjects', icon: 'subjects' },
        { page: 'subjects', label: 'Subjects', icon: 'subjects' },
    ] },
    { label: 'ACADEMIC OPERATIONS', items: [{page:'student-attendance',label:'Student Attendance',icon:'calendar'},{page:'staff-attendance',label:'Staff Attendance',icon:'staff'},{page:'timetables',label:'Timetable',icon:'clock'},{page:'teacher-timetable',label:'Teacher Timetable',icon:'clock'},{page:'periods',label:'School Periods',icon:'clock'},{page:'working-days',label:'Working Days',icon:'calendar'},{page:'examinations',label:'Examinations',icon:'academics'},{page:'exam-types',label:'Exam Types',icon:'academics'},{page:'schedules',label:'Exam Scheduling',icon:'calendar'},{page:'marks',label:'Marks Entry',icon:'subjects'},{page:'grading',label:'Grading',icon:'subjects'},{page:'results',label:'Results & Report Cards',icon:'academics'},{page:'reports',label:'Academic Reports',icon:'finance'},{page:'audit',label:'Audit Trail',icon:'shield'}] },
    { label: 'STUDENT SERVICES', items: [
        { page: 'parents', label: 'Parents', icon: 'parents' }, { page: 'clubs', label: 'Clubs & Sports', icon: 'sports' },
        { page: 'communications', label: 'Communications', icon: 'messages' },
    ] },
    { label: 'FINANCE OFFICE', items: [
        { page: 'accounts', label: 'Accounts', icon: 'wallet' }, { page: 'fees', label: 'Fee Collection', icon: 'fees' },
        { page: 'expenses', label: 'Expenses', icon: 'receipt' }, { page: 'accounting', label: 'Accounting', icon: 'finance' },
    ] },
    { label: 'ADMINISTRATION', items: [
        { page: 'transport', label: 'Transport', icon: 'bus' }, { page: 'staff', label: 'Staff', icon: 'staff' },
        { page: 'marketing', label: 'Marketing', icon: 'marketing' }, { page: 'campuses', label: 'Campuses', icon: 'school' },
        { page: 'school-settings', label: 'Settings', icon: 'settings' },
    ] },
];
export const platformLinks = [
    { page: 'overview', label: 'Platform dashboard', href: '/preview', icon: 'platform' },
    { page: 'schools', label: 'Schools directory', href: '/preview/schools', icon: 'school' },
    { page: 'plans', label: 'Subscription plans', href: '/preview/plans', icon: 'plans' },
    { page: 'settings', label: 'System settings', href: '/preview/settings', icon: 'settings' },
];
export const schoolPages = ['profile', 'attendance', 'examinations', ...schoolGroups.flatMap(g => g.items.map(i => i.page))];
let academicNavigation = false;
let financeNavigation = false;
export function configureFinanceNavigation(enabled:boolean){financeNavigation=enabled;}
export function configureAcademicNavigation(enabled: boolean) { academicNavigation = enabled; }
export function schoolHref(slug: string | undefined, page: string): string { if(slug && financeNavigation && (page.startsWith('finance-') || ['accounts','fees','expenses','accounting'].includes(page))){const target=page.startsWith('finance-')?page.slice(8):({accounts:'dashboard',fees:'payments',expenses:'expenses',accounting:'reports'} as Record<string,string>)[page];return `/preview/schools/${slug}/finance/${target}`;} const operations: Record<string,string>={attendance:'student-attendance','student-attendance':'student-attendance','staff-attendance':'staff-attendance',timetables:'timetable',timetable:'timetable','teacher-timetable':'teacher-timetable',periods:'periods','working-days':'working-days',examinations:'exams','exam-types':'exam-types',exams:'exams',schedules:'schedules',marks:'marks',grading:'grading',results:'results',reports:'reports',audit:'audit'}; if(slug && academicNavigation && operations[page])return `/preview/schools/${slug}/academic/operations/${operations[page]}`; const resources: Record<string,string> = {students:'students',classes:'classes',sections:'sections',academics:'sessions',subjects:'subjects',teachers:'teachers',staff:'teachers','teacher-assignments':'teacher-assignments',enrollments:'enrollments','class-subjects':'class-subjects',sessions:'sessions'}; return slug ? (academicNavigation && resources[page] ? `/preview/schools/${slug}/academic/${resources[page]}` : `/preview/schools/${slug}/${page}`) : '/preview/schools'; }
