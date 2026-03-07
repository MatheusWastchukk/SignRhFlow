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
  status: 'DRAFT' | 'PENDING' | 'SIGNED' | 'REJECTED';
  delivery_method: 'EMAIL' | 'WHATSAPP';
  file_path: string;
  employee?: Employee;
}

export interface PaginatedResponse<T> {
  data: T[];
}
