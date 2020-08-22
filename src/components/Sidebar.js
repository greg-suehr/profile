import React from 'react';
import Logo from './Logo.js';

const Sidebar = () => {
	return (
		<div className=".Sidebar">
		  <Logo />
		  <h1>Greg Suehr</h1>
		    <a href="#">Introduction</a>
		    <a href="#">About</a>
		    <a href="#">Resume</a>
		 	<a href="#">Pictures</a>
		  	<a href="#">Social</a>
		</div>
		)
}

export default Sidebar;