import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Settings from './pages/Settings.jsx';
import NewInvoice from './pages/NewInvoice.jsx';
import NewBoleta from './pages/NewBoleta.jsx';
import NewCreditNote from './pages/NewCreditNote.jsx';
import NewDebitNote from './pages/NewDebitNote.jsx';
import NewDispatchGuide from './pages/NewDispatchGuide.jsx';
import DocumentList from './pages/DocumentList.jsx';
import Summaries from './pages/Summaries.jsx';
import Login from './pages/Login.jsx';

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Layout />}>
        <Route path="/" element={<Dashboard />} />
        <Route path="/configuracion" element={<Settings />} />
        <Route path="/nueva-factura" element={<NewInvoice />} />
        <Route path="/nueva-boleta" element={<NewBoleta />} />
        <Route path="/nueva-nc" element={<NewCreditNote />} />
        <Route path="/nueva-nd" element={<NewDebitNote />} />
        <Route path="/nueva-guia" element={<NewDispatchGuide />} />
        <Route path="/documentos/:tipo" element={<DocumentList />} />
        <Route path="/resumenes" element={<Summaries />} />
        <Route path="*" element={<Navigate to="/" />} />
      </Route>
    </Routes>
  );
}
