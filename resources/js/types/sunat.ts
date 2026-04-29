export type TenantSunat = {
    ruc: string;
    razon_social: string;
    environment: 'beta' | 'produccion';
    sol_user?: string;
    sol_configurado?: boolean;
    serie_factura?: string;
    serie_boleta?: string;
};

export type SunatStatus = 'borrador' | 'pendiente' | 'enviado' | 'aceptado' | 'rechazado';

export type DocumentoSunat = {
    id: number;
    tipo_doc: '01' | '03' | '07' | '08';
    serie: string;
    correlativo: number;
    numero: string;
    cliente: string;
    fecha: string;
    total: number;
    moneda: 'PEN' | 'USD' | 'EUR';
    estado: SunatStatus;
    tiene_pdf: boolean;
    tiene_xml: boolean;
};

export type SerieSunat = {
    id: number;
    serie: string;
    tipo_documento: string;
    correlativo: number;
};

export type ClienteSunat = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    razon_social: string;
    direccion?: string | null;
    email?: string | null;
};

export type ItemForm = {
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    descuento_pct: number;
    tip_afe_igv: string;
};

export type CuotaForm = {
    numero: number;
    fecha_vencimiento: string;
    monto: number;
};

export type DetraccionForm = {
    enabled: boolean;
    codigo: string;
    descripcion: string;
    porcentaje: number;
    cuenta: string;
};

export type StatsPanel = {
    total_docs: number;
    monto_total_pen: number;
    aceptados: number;
    rechazados: number;
    pendientes: number;
};

export type MotivoNC = {
    codigo: string;
    descripcion: string;
};

export type FlashProps = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};
