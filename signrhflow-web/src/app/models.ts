export interface Employee {
  id: string;
  name: string;
  email: string;
  phone: string;
  cpf: string;
}

export interface Contract {
  id: string;
  employee_id: string;
  autentique_document_id: string | null;
  signing_token?: string | null;
  signing_token_expires_at?: string | null;
  status: 'DRAFT' | 'PENDING' | 'SIGNED' | 'REJECTED';
  delivery_method: 'EMAIL' | 'WHATSAPP';
  file_path: string;
  employee?: Employee;
}

export interface SigningContextResponse {
  contract: {
    id: string;
    status: 'DRAFT' | 'PENDING' | 'SIGNED' | 'REJECTED';
    delivery_method: 'EMAIL' | 'WHATSAPP';
    file_path: string;
    pdf_url: string;
    signing_token_expires_at: string | null;
  };
  employee: {
    name: string | null;
  };
}

export interface PaginatedResponse<T> {
  data: T[];
}
