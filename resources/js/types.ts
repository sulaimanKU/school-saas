export interface School {
    slug: string; name: string; city: string; status: string; students: number; teachers: number;
    classrooms: number; color: string; trend: number[]; plan: string; monthly_price_minor: number;
    domain: string | null; settings: Record<string, string>;
}
export interface Plan { code: string; name: string; monthly_price_minor: number; currency: string; capacity: number; features: string[]; schools: number }
export interface SchoolDashboard {
    sample: boolean;
    gender: { boys: number; girls: number };
    classes: { name: string; grade: string; boys: number; girls: number; students: number }[];
    distribution: { name: string; boys: number; girls: number; students: number }[];
    parents: number;
    staff: { teachers: number; support: number };
    finance: { series: { expected_minor: number; collected_minor: number; pending_minor: number }[]; expected_minor: number; collected_minor: number; pending_minor: number; overdue_groups: {label:string;period:string;amount_minor:number}[] };
    enrollment: { new: number; previous_total: number; cohorts: { label: string; count: number }[] };
    events: { day: string; month: string; title: string; time: string; location: string; type: string }[];
    activities: { title: string; detail: string; time: string; type: string }[];
}
export interface LiveAcademic {
    operations?: Record<string,any>;
    source: string; session: {id:number;name:string}|null; sessions:{id:number;name:string}[];
    students:number; classes:number; teachers:number; gender:{boys:number;girls:number;other:number};
    distribution:{name:string;boys:number;girls:number;students:number}[]; new_admissions:number; months:string[]; trend:number[];
}
