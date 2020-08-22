import React from 'react';
import './App.css';
import Sidebar from './components/Sidebar.js'
import Home from './components/Home.js'

function App() {
  return (
    <div className="App">
      <Sidebar />
      <Home />
    </div>
  );
}

export default App;
